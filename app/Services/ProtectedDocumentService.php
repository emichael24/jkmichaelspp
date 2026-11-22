<?php

namespace App\Services;

use App\Exceptions\DocumentPreparing;
use App\Jobs\PrepareDocument;
use App\Jobs\RenderDocumentPages;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use ZipArchive;

class ProtectedDocumentService
{
    public const PPTX = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';

    public function supports(MediaAsset $asset): bool
    {
        return $asset->kind === 'document' && in_array($asset->mime, ['application/pdf', self::PPTX], true);
    }

    /** @var array<string, bool> */
    private static array $executables = [];

    /**
     * Server rendering (watermarked PNG pages) needs Poppler, GD and, for PowerPoint,
     * LibreOffice. When they are not installed the reader falls back to rendering in
     * the learner's browser instead of failing.
     */
    public function serverRenderingAvailable(MediaAsset $asset): bool
    {
        $mode = config('documents.renderer', 'auto');
        if ($mode === 'browser') {
            return false;
        }
        if ($mode === 'server') {
            return true;
        }
        $tools = ['pdfinfo', 'pdftoppm'];
        if ($asset->mime === self::PPTX && ! is_file($this->directory($asset).'/presentation.pdf')) {
            $tools[] = 'libreoffice';
        }

        return function_exists('imagecreatefrompng') && collect($tools)->every(fn ($tool) => $this->executable((string) config('documents.'.$tool)));
    }

    public function browserRenderingAllowed(): bool
    {
        return config('documents.renderer', 'auto') !== 'server';
    }

    /** Requirements report used by `php artisan documents:check`. */
    public function diagnostics(): array
    {
        return [
            'renderer' => config('documents.renderer', 'auto'),
            'gd' => function_exists('imagecreatefrompng'),
            'zip' => class_exists(ZipArchive::class),
            'pdfinfo' => $this->executable((string) config('documents.pdfinfo')),
            'pdftoppm' => $this->executable((string) config('documents.pdftoppm')),
            'libreoffice' => $this->executable((string) config('documents.libreoffice')),
        ];
    }

    private function executable(string $command): bool
    {
        if ($command === '') {
            return false;
        }

        if (basename($command) === $command) {
            return self::$executables[$command] ??= (new ExecutableFinder)->find($command) !== null;
        }
        // Plesk's PHP-FPM open_basedir forbids inspecting system binaries such as
        // /usr/local/bin/jkm-pdfinfo even though running them works. An absolute path
        // PHP may not inspect is treated as available; if conversion then fails, the
        // reader falls back to browser rendering.
        if (! self::inspectable($command, (string) ini_get('open_basedir'))) {
            return true;
        }

        return self::$executables[$command] ??= is_file($command) && is_executable($command);
    }

    /** Whether open_basedir lets PHP stat this path (an empty open_basedir allows everything). */
    public static function inspectable(string $path, string $openBasedir): bool
    {
        if ($openBasedir === '') {
            return true;
        }
        foreach (explode(PATH_SEPARATOR, $openBasedir) as $allowed) {
            $allowed = rtrim($allowed, '/\\');
            if ($allowed !== '' && ($path === $allowed || str_starts_with($path, $allowed.'/') || str_starts_with($path, $allowed.'\\'))) {
                return true;
            }
        }

        return false;
    }

    public function watermark(User $user): string
    {
        return 'JK MICHAELS | Viewer #'.$user->id.' | '.now()->utc()->format('Y-m-d H:i').' UTC';
    }

    /** Manifest for in-browser rendering; the reader fetches the source through {@see source()}. */
    public function browserManifest(MediaAsset $asset, User $user): array
    {
        $this->assertReadable($asset);
        $source = $this->source($asset);

        return ['mode' => 'browser', 'format' => $source['format'], 'watermark' => $this->watermark($user)];
    }

    /**
     * Source bytes for the browser renderer. A presentation already converted on the
     * server is served as its PDF, which renders more faithfully than raw PPTX.
     *
     * @return array{path: string, mime: string, format: string}
     */
    public function source(MediaAsset $asset): array
    {
        $this->assertReadable($asset);
        $original = $this->originalPath($asset);
        abort_unless(is_file($original), 404);
        if ($asset->mime === self::PPTX) {
            $converted = $this->directory($asset).'/presentation.pdf';
            if (is_file($converted)) {
                return ['path' => $converted, 'mime' => 'application/pdf', 'format' => 'pdf'];
            }

            return ['path' => $original, 'mime' => self::PPTX, 'format' => 'presentation'];
        }

        return ['path' => $original, 'mime' => 'application/pdf', 'format' => 'pdf'];
    }

    private function assertReadable(MediaAsset $asset): void
    {
        abort_if(config('media.require_scan') && ! $asset->scanned_at, 423, 'This document is awaiting its security scan.');
        abort_unless($this->supports($asset), 422, 'Upload a PDF or PowerPoint presentation for the protected reader.');
    }

    /**
     * Page count and format for the reader. With DOCUMENT_CONVERSION=queue (the default)
     * conversion runs in the queue worker: Plesk's PHP-FPM service forbids the namespaces
     * the converter sandbox needs, so web requests only serve cached results.
     *
     * @throws DocumentPreparing while the worker is still converting
     */
    public function metadata(MediaAsset $asset): array
    {
        $this->assertReadable($asset);
        $directory = $this->directory($asset);
        if ($cached = $this->cachedManifest($directory)) {
            return $cached;
        }
        if (! $this->queued()) {
            return $this->prepareMetadata($asset);
        }
        $this->assertNotFailed($directory);
        PrepareDocument::dispatch($asset->id);
        // A synchronous queue (local development, tests) has already finished the work.
        if ($cached = $this->cachedManifest($directory)) {
            return $cached;
        }
        $this->assertNotFailed($directory);
        throw new DocumentPreparing;
    }

    /** Convert (PPTX) and inspect the document. Runs where the sandbox may create namespaces. */
    public function prepareMetadata(MediaAsset $asset): array
    {
        $this->assertReadable($asset);
        $directory = $this->directory($asset);

        return $this->locked($directory.'/prepare.lock', function () use ($asset, $directory) {
            if ($cached = $this->cachedManifest($directory)) {
                return $cached;
            }
            $source = $this->originalPath($asset);
            abort_unless(is_file($source), 404);
            if ($asset->mime === self::PPTX) {
                $this->validatePresentation($source);
                $input = $directory.DIRECTORY_SEPARATOR.'presentation.pptx';
                abort_unless(copy($source, $input), 503, 'Document preparation is unavailable.');
                // Each conversion has its own profile; it cannot attach to an interactive Office process.
                $profile = $directory.'/profile';
                if (! is_dir($profile.'/user')) {
                    mkdir($profile.'/user', 0700, true);
                }
                file_put_contents($profile.'/user/registrymodifications.xcu', '<?xml version="1.0"?><oor:items xmlns:oor="http://openoffice.org/2001/registry"><item oor:path="/org.openoffice.Office.Common/Security/Scripting"><prop oor:name="MacroSecurityLevel" oor:op="fuse"><value>3</value></prop></item></oor:items>');
                $profileUri = 'file://'.(PHP_OS_FAMILY === 'Windows' ? '/' : '').str_replace(['%2F', '%3A'], ['/', ':'], rawurlencode(str_replace('\\', '/', $profile)));
                $this->run([config('documents.libreoffice'), '-env:UserInstallation='.$profileUri, '--headless', '--nologo', '--nodefault', '--norestore', '--convert-to', 'pdf:impress_pdf_Export', '--outdir', $directory, $input], config('documents.convert_timeout'));
                abort_unless(is_file($directory.'/presentation.pdf') && filesize($directory.'/presentation.pdf') <= 100 * 1024 * 1024, 422, 'The presentation could not be prepared. Export it to PDF and upload again.');
            }
            $pdf = $this->pdfPath($asset, $directory);
            $info = $this->run([config('documents.pdfinfo'), $pdf], 15);
            abort_unless(preg_match('/^Pages:\s+(\d+)/m', $info, $pages), 422, 'The document could not be read.');
            abort_if(preg_match('/^Encrypted:\s+yes/m', $info), 422, 'Upload a document without a password.');
            $count = (int) $pages[1];
            abort_unless($count > 0 && $count <= config('documents.max_pages'), 422, 'Documents must contain 1 to '.config('documents.max_pages').' pages or slides.');
            $metadata = ['mode' => 'raster', 'pages' => $count, 'format' => $asset->mime === self::PPTX ? 'presentation' : 'pdf'];
            file_put_contents($directory.'/manifest.json', json_encode($metadata, JSON_THROW_ON_ERROR), LOCK_EX);
            @unlink($directory.'/failed.json');

            return $metadata;
        });
    }

    /**
     * A watermarked page image for this viewer.
     *
     * @throws DocumentPreparing while the worker is still rendering the page
     */
    public function page(MediaAsset $asset, int $page, User $user): string
    {
        abort_unless(function_exists('imagecreatefrompng'), 503, 'The document reader is temporarily unavailable.');
        $metadata = $this->metadata($asset);
        abort_unless($page >= 1 && $page <= $metadata['pages'], 404);
        $path = $this->directory($asset).'/page-'.$page.'.png';
        $ahead = min($metadata['pages'], $page + self::PREFETCH);
        if (! $this->queued()) {
            $this->renderPage($asset, $page);
        } elseif (! is_file($path)) {
            RenderDocumentPages::dispatch($asset->id, $page, $ahead);
            if (! is_file($path)) {
                throw new DocumentPreparing;
            }
        } elseif ($page < $ahead && ! is_file($this->directory($asset).'/page-'.$ahead.'.png')) {
            // Keep a few pages ready ahead of the reader.
            RenderDocumentPages::dispatch($asset->id, $page + 1, $ahead);
        }
        // Burn identification into the pixels; removing an HTML overlay cannot remove the watermark.
        $image = @imagecreatefrompng($path);
        abort_unless($image, 503, 'This page could not be displayed.');
        try {
            $width = imagesx($image);
            $height = imagesy($image);
            $mark = $this->watermark($user);
            $ink = imagecolorallocatealpha($image, 90, 105, 125, 72);
            for ($y = 90; $y < $height; $y += 270) {
                imagestring($image, 5, max(12, (int) (($width - strlen($mark) * 9) / 2)), $y, $mark, $ink);
            }
            $footer = imagecolorallocate($image, 35, 45, 65);
            imagestring($image, 3, 14, max(0, $height - 22), $mark.' | '.$page.'/'.$metadata['pages'], $footer);
            ob_start();
            imagepng($image);

            return ob_get_clean();
        } finally {
            imagedestroy($image);
        }
    }

    public const PREFETCH = 4;

    /** Rasterize one page into the private cache (no watermark). Runs where the sandbox works. */
    public function renderPage(MediaAsset $asset, int $page): void
    {
        $metadata = $this->prepareMetadata($asset);
        abort_unless($page >= 1 && $page <= $metadata['pages'], 404);
        $directory = $this->directory($asset);
        $path = $directory.'/page-'.$page.'.png';
        $this->locked($directory.'/page-'.$page.'.lock', function () use ($asset, $page, $directory, $path) {
            if (is_file($path)) {
                return;
            }
            $prefix = $directory.'/render-'.$page;
            $this->run([config('documents.pdftoppm'), '-f', (string) $page, '-l', (string) $page, '-singlefile', '-scale-to', (string) config('documents.page_pixels'), '-png', $this->pdfPath($asset, $directory), $prefix], config('documents.page_timeout'));
            abort_unless(is_file($prefix.'.png') && rename($prefix.'.png', $path), 503, 'This page could not be prepared. Please retry.');
        });
    }

    public function pageCached(MediaAsset $asset, int $page): bool
    {
        return is_file($this->directory($asset).'/page-'.$page.'.png');
    }

    /** Remember a conversion failure so readers fall back instead of waiting; retried after ten minutes. */
    public function recordFailure(MediaAsset $asset, string $message): void
    {
        file_put_contents($this->directory($asset).'/failed.json', json_encode(['message' => $message, 'at' => time()], JSON_THROW_ON_ERROR), LOCK_EX);
    }

    private function assertNotFailed(string $directory): void
    {
        $file = $directory.'/failed.json';
        if (! is_file($file)) {
            return;
        }
        $failure = json_decode((string) file_get_contents($file), true) ?: [];
        if (($failure['at'] ?? 0) < time() - 600) {
            @unlink($file);

            return;
        }
        abort(422, $failure['message'] ?? 'The document could not be prepared.');
    }

    private function cachedManifest(string $directory): ?array
    {
        $manifest = $directory.'/manifest.json';

        return is_file($manifest) ? json_decode(file_get_contents($manifest), true, 512, JSON_THROW_ON_ERROR) : null;
    }

    private function queued(): bool
    {
        return config('documents.conversion', 'queue') === 'queue';
    }

    public function validatePresentation(string $path): void
    {
        abort_unless(class_exists(ZipArchive::class), 503, 'Presentation conversion is unavailable.');
        $zip = new ZipArchive;
        abort_unless($zip->open($path) === true, 422, 'Invalid PowerPoint presentation.');
        try {
            abort_unless($zip->numFiles > 0 && $zip->numFiles <= 5000 && $zip->locateName('ppt/presentation.xml') !== false && $zip->locateName('[Content_Types].xml') !== false, 422, 'Invalid PowerPoint presentation.');
            $types = $zip->getFromName('[Content_Types].xml', 1024 * 1024);
            abort_unless(str_contains($types, 'application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml'), 422, 'Invalid PowerPoint presentation content type.');
            $bytes = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $name = $entry['name'];
                $bytes += $entry['size'];
                $safePath = $name !== '' && strlen($name) <= 240 && ! preg_match('~(?:^/|(^|/)\.\.?(/|$)|[\\\\:\x00-\x1f])~', $name);
                abort_unless($safePath && $bytes <= 150 * 1024 * 1024 && $entry['size'] <= 30 * 1024 * 1024 && ($entry['encryption_method'] ?? 0) === 0, 422, 'The presentation contains unsafe or oversized content.');
                $this->assertNoActiveContent($zip, $i, $name);
                if (str_ends_with($name, '.rels') || str_ends_with($name, '.xml')) {
                    $xml = $zip->getFromIndex($i);
                    abort_if(preg_match('/<!DOCTYPE|<!ENTITY/i', $xml), 422, 'Unsafe presentation XML.');
                    if (str_ends_with($name, '.rels')) {
                        $doc = new \DOMDocument;
                        $old = libxml_use_internal_errors(true);
                        try {
                            $valid = $doc->loadXML($xml, LIBXML_NONET);
                        } finally {
                            libxml_clear_errors();
                            libxml_use_internal_errors($old);
                        }
                        abort_unless($valid, 422, 'Invalid presentation relationships.');
                        foreach ($doc->getElementsByTagName('Relationship') as $relationship) {
                            // Hyperlinks are inert in raster output; externally linked media must be embedded.
                            $target = rawurldecode($relationship->getAttribute('Target'));
                            if (strtolower($relationship->getAttribute('TargetMode')) === 'external') {
                                abort_unless(str_ends_with($relationship->getAttribute('Type'), '/hyperlink') && in_array(strtolower(parse_url($target, PHP_URL_SCHEME) ?? ''), ['http', 'https', 'mailto']), 422, 'Embed linked images, audio and other external files before uploading.');

                                continue;
                            }
                            abort_if($target === '' || preg_match('~^/|[\\\\:\x00-\x1f]~', $target), 422, 'Unsafe presentation relationship.');
                            $base = dirname(dirname($name));
                            $parts = $base === '.' ? [] : explode('/', $base);
                            foreach (explode('/', explode('#', $target, 2)[0]) as $part) {
                                if ($part === '..') {
                                    abort_unless(count($parts), 422, 'Unsafe presentation relationship.');
                                    array_pop($parts);
                                } elseif ($part !== '.' && $part !== '') {
                                    $parts[] = $part;
                                }
                            }
                            abort_unless($zip->locateName(implode('/', $parts)) !== false, 422, 'The presentation contains a broken internal reference.');
                        }
                    }
                }
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * Macros, ActiveX and OLE objects are refused. Native PowerPoint charts keep their
     * data as an embedded .xlsx under ppt/embeddings/; macro-free OOXML packages there
     * are allowed after checking they carry no macros of their own.
     */
    private function assertNoActiveContent(ZipArchive $zip, int $index, string $name): void
    {
        $lower = strtolower($name);
        abort_if(str_contains($lower, 'vbaproject'), 422, 'This presentation contains macros. Save it as a regular PowerPoint presentation (.pptx), not macro-enabled (.pptm), and upload again.');
        abort_if(str_contains($lower, 'activex'), 422, 'This presentation contains ActiveX controls. Remove them in PowerPoint and upload again.');
        if (str_starts_with($lower, 'ppt/embeddings/')) {
            $object = basename($name);
            abort_unless(preg_match('~^[^/]+\.(xlsx|docx|pptx)$~', $object), 422,
                "This presentation contains an embedded object ({$object}). In PowerPoint, replace it with a picture (copy it, then Paste Special → Picture) and upload again. Charts and tables are fine.");
            $this->assertMacroFreePackage((string) $zip->getFromIndex($index), $object);

            return;
        }
        abort_if(str_ends_with($lower, '.bin') && ! preg_match('~^ppt/printersettings/printersettings\d+\.bin$~', $lower), 422,
            'This presentation contains an embedded binary object ('.basename($name).'). Remove it in PowerPoint and upload again.');
    }

    private function assertMacroFreePackage(string $bytes, string $object): void
    {
        $temp = tempnam(sys_get_temp_dir(), 'jkm-embed');
        try {
            file_put_contents($temp, $bytes);
            $inner = new ZipArchive;
            abort_unless($inner->open($temp) === true, 422, "The embedded file {$object} is not a valid Office document.");
            try {
                for ($i = 0; $i < min($inner->numFiles, 5000); $i++) {
                    $entry = strtolower((string) $inner->getNameIndex($i));
                    abort_if(str_contains($entry, 'vbaproject') || str_contains($entry, 'activex') || str_contains($entry, 'embeddings/') || (str_ends_with($entry, '.bin') && ! str_contains($entry, 'printersettings')), 422,
                        "The embedded file {$object} contains macros or objects. Replace it with a picture in PowerPoint and upload again.");
                }
            } finally {
                $inner->close();
            }
        } finally {
            @unlink($temp);
        }
    }

    private function directory(MediaAsset $asset): string
    {
        $path = Storage::disk('local')->path('document-previews/'.$asset->id.'/'.substr($asset->sha256, 0, 16));
        if (! is_dir($path)) {
            mkdir($path, 0700, true);
        }

        return realpath($path) ?: $path;
    }

    private function pdfPath(MediaAsset $asset, string $directory): string
    {
        return $asset->mime === self::PPTX ? $directory.'/presentation.pdf' : $this->originalPath($asset);
    }

    /**
     * Fully resolved source path. Production storage sits behind release symlinks and the
     * converter sandbox refuses any path that is not already canonical.
     */
    private function originalPath(MediaAsset $asset): string
    {
        $path = Storage::disk('local')->path($asset->path);

        return realpath($path) ?: $path;
    }

    private function locked(string $path, callable $work): mixed
    {
        $handle = fopen($path, 'c');
        abort_unless($handle, 503, 'Document preparation is unavailable.');
        try {
            abort_unless(flock($handle, LOCK_EX | LOCK_NB), 409, 'This document is being prepared. Please retry in a moment.');

            return $work();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function run(array $command, int $timeout): string
    {
        // Do not pass request/CGI variables or application credentials into document parsers.
        $allowed = ['PATH', 'PATHEXT', 'SYSTEMROOT', 'WINDIR', 'COMSPEC', 'TEMP', 'TMP', 'HOME', 'USERPROFILE', 'APPDATA', 'LOCALAPPDATA', 'LANG', 'LC_ALL', 'PROGRAMFILES', 'PROGRAMFILES(X86)', 'PROGRAMW6432'];
        $environment = [];
        $inherited = getenv();
        foreach (array_unique(array_merge(array_keys($inherited), array_keys($_SERVER), array_keys($_ENV))) as $key) {
            $environment[$key] = false;
        }
        // HTTP SAPIs may expose only request variables in $_SERVER. Supply the OS
        // paths explicitly rather than relying on Process's inferred environment.
        foreach ($inherited as $key => $value) {
            if (in_array(strtoupper($key), $allowed, true)) {
                $environment[$key] = $value;
            }
        }
        $environment['LC_ALL'] = 'C';
        $process = new Process($command, base_path(), $environment);
        $process->setTimeout($timeout);
        try {
            $process->run();
        } catch (ExceptionInterface $exception) {
            report($exception);
            abort(503, 'Document preparation is unavailable. Please retry or contact support.');
        }
        if (! $process->isSuccessful()) {
            logger()->warning('Document conversion failed', ['program' => $command[0], 'exit_code' => $process->getExitCode(), 'diagnostic' => substr($process->getErrorOutput(), 0, 1500)]);
            abort(422, 'The document could not be prepared. Check the file or contact support.');
        }

        return $process->getOutput();
    }
}
