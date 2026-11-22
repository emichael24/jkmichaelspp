<?php

namespace App\Services;

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

        return self::$executables[$command] ??= (basename($command) !== $command)
            ? is_file($command) && is_executable($command)
            : (new ExecutableFinder)->find($command) !== null;
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
        $original = Storage::disk('local')->path($asset->path);
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

    public function metadata(MediaAsset $asset): array
    {
        abort_if(config('media.require_scan') && ! $asset->scanned_at, 423, 'This document is awaiting its security scan.');
        abort_unless($this->supports($asset), 422, 'Upload a PDF or PowerPoint presentation for the protected reader.');
        $directory = $this->directory($asset);

        return $this->locked($directory.'/prepare.lock', function () use ($asset, $directory) {
            $manifest = $directory.'/manifest.json';
            if (is_file($manifest)) {
                return json_decode(file_get_contents($manifest), true, 512, JSON_THROW_ON_ERROR);
            }
            $source = Storage::disk('local')->path($asset->path);
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
            file_put_contents($manifest, json_encode($metadata, JSON_THROW_ON_ERROR), LOCK_EX);

            return $metadata;
        });
    }

    public function page(MediaAsset $asset, int $page, User $user): string
    {
        abort_unless(function_exists('imagecreatefrompng'), 503, 'The document reader is temporarily unavailable.');
        $metadata = $this->metadata($asset);
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
                $activeContent = preg_match('~(?:vbaProject|activeX|embeddings)~i', $name)
                    || (str_ends_with(strtolower($name), '.bin') && ! preg_match('~^ppt/printerSettings/printerSettings\d+\.bin$~i', $name));
                abort_if($activeContent, 422, 'Remove macros, embedded objects and active content before uploading.');
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
        return $asset->mime === self::PPTX ? $directory.'/presentation.pdf' : Storage::disk('local')->path($asset->path);
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
