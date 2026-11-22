<?php

namespace App\Services;

use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Process;
use ZipArchive;

class PrivateMediaService
{
    public function scan(string $path): ?Carbon
    {
        if (! config('media.require_scan')) {
            return null;
        }
        // ClamAV normally assumes oversized files are clean. Exceeded limits,
        // encrypted containers and incomplete scans must fail closed instead.
        $scan = new Process([config('media.scanner'), '--no-summary', '--heuristic-alerts=yes',
            '--alert-exceeds-max=yes', '--alert-encrypted=yes', '--alert-macros=yes',
            '--max-filesize=500M', '--max-scansize=1200M', '--max-files=5000',
            '--max-recursion=16', '--max-scantime=110000', '--', $path]);
        $scan->setTimeout(120);
        try {
            $scan->run();
        } catch (ExceptionInterface $exception) {
            report($exception);
            abort(422, 'The file security scan did not finish. Please retry or contact support.');
        }
        abort_unless($scan->isSuccessful() && trim($scan->getErrorOutput()) === '' &&
            preg_match('/: OK\s*$/', $scan->getOutput()), 422, 'The file could not pass the security scan. Contact support.');

        return now();
    }

    public function video(UploadedFile $file, User $user): MediaAsset
    {
        return $this->videoPath($file->getRealPath(), $user);
    }

    public function document(UploadedFile $file, User $user): MediaAsset
    {
        $mime = $file->getMimeType();
        abort_unless(in_array($mime, ['application/pdf', 'text/plain', 'text/csv', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.openxmlformats-officedocument.presentationml.presentation']) && $file->getSize() <= 20 * 1024 * 1024, 422, 'Unsupported resource file.');
        $scanned = $this->scan($file->getRealPath());
        $id = (string) Str::uuid();
        $path = $file->storeAs('media/'.$id, 'document', 'local');
        abort_unless($path, 503);

        return MediaAsset::create(['id' => $id, 'user_id' => $user->id, 'kind' => 'document', 'path' => $path, 'mime' => $mime,
            'bytes' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath()), 'scanned_at' => $scanned]);
    }

    public function authoringFile(UploadedFile $file, User $user, string $type): MediaAsset
    {
        $mime = $file->getMimeType();
        // Some libmagic versions identify OOXML presentations as their ZIP container.
        // The complete presentation structure is checked below before accepting this type.
        if ($type === 'slides' && strtolower($file->getClientOriginalExtension()) === 'pptx' && in_array($mime, ['application/zip', 'application/x-zip', 'application/x-zip-compressed', 'application/octet-stream'])) {
            $mime = ProtectedDocumentService::PPTX;
        }
        $allowed = match ($type) {
            'image' => ['image/jpeg', 'image/png', 'image/webp'],
            'audio' => ['audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/ogg', 'audio/wav', 'audio/x-wav'],
            'pdf' => ['application/pdf'],
            'slides' => ['application/pdf', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            default => [],
        };
        $maxBytes = match ($type) {
            'image' => 8, 'audio' => 50, default => 20
        } * 1024 * 1024;
        abort_unless(in_array($mime, $allowed) && $file->getSize() <= $maxBytes, 422, 'The file format or size is not supported for this lesson.');
        if ($mime === ProtectedDocumentService::PPTX) {
            app(ProtectedDocumentService::class)->validatePresentation($file->getRealPath());
        }
        if ($type === 'image') {
            $size = @getimagesize($file->getRealPath());
            abort_unless($size && $size[0] <= 12000 && $size[1] <= 12000, 422, 'Use a valid image up to 12,000 pixels on each side.');
        }
        $scanned = $this->scan($file->getRealPath());
        $id = (string) Str::uuid();
        $path = $file->storeAs('media/'.$id, $type, 'local');
        abort_unless($path, 503, 'Unable to store the upload.');

        return MediaAsset::create(['id' => $id, 'user_id' => $user->id,
            'kind' => in_array($type, ['pdf', 'slides']) ? 'document' : $type,
            'path' => $path, 'mime' => $mime, 'bytes' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()), 'scanned_at' => $scanned]);
    }

    public function videoPath(string $source, User $user): MediaAsset
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($source);
        abort_unless(in_array($mime, ['video/mp4', 'video/quicktime', 'video/webm']) && filesize($source) <= config('media.max_bytes'), 422, 'Invalid video file.');
        $scanned = $this->scan($source);
        $id = (string) Str::uuid();
        $path = 'media/'.$id.'/video';
        $stream = fopen($source, 'rb');
        try {
            abort_unless(Storage::disk('local')->put($path, $stream), 503, 'Unable to store upload.');
        } finally {
            fclose($stream);
        }

        return MediaAsset::create(['id' => $id, 'user_id' => $user->id, 'kind' => 'video', 'path' => $path, 'mime' => $mime,
            'bytes' => filesize($source), 'sha256' => hash_file('sha256', $source), 'scanned_at' => $scanned]);
    }

    public function scorm(UploadedFile $file, User $user): MediaAsset
    {
        abort_unless(class_exists(ZipArchive::class), 503, 'SCORM uploads require the ZIP extension.');
        $scanned = $this->scan($file->getRealPath());
        $zip = new ZipArchive;
        abort_unless($zip->open($file->getRealPath()) === true, 422, 'Invalid ZIP archive.');
        $id = (string) Str::uuid();
        $directory = 'media/'.$id;
        try {
            $this->check($zip->numFiles > 0 && $zip->numFiles <= config('media.scorm_max_entries'), 'The archive has too many entries.');
            $total = 0;
            $seen = [];
            $manifest = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $name = $entry['name'];
                $this->check($this->safePath($name), 'Unsafe archive path.');
                $key = strtolower(rtrim($name, '/'));
                $this->check(! isset($seen[$key]), 'Duplicate archive path.');
                $seen[$key] = true;
                $zip->getExternalAttributesIndex($i, $os, $attributes);
                $this->check((($attributes >> 16) & 0170000) !== 0120000, 'Symbolic links are prohibited.');
                $this->check(($entry['encryption_method'] ?? 0) === 0, 'Encrypted archives are unsupported.');
                $total += $entry['size'];
                $this->check($total <= config('media.scorm_max_expanded_bytes') && $entry['size'] <= 100 * 1024 * 1024, 'Expanded archive is too large.');
                $this->check($entry['size'] < 1024 * 1024 || $entry['size'] / max(1, $entry['comp_size']) <= 100, 'Unsafe compression ratio.');
                if (! str_ends_with($name, '/')) {
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    $this->check(in_array($ext, ['html', 'htm', 'js', 'css', 'json', 'xml', 'xsd', 'dtd', 'txt', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico', 'mp4', 'mp3', 'm4a', 'ogg', 'wav', 'webm', 'woff', 'woff2', 'ttf', 'eot', 'pdf', 'vtt', 'srt']), 'Unsupported file in archive.');
                    if (strtolower(basename($name)) === 'imsmanifest.xml') {
                        $this->check($manifest === null, 'Multiple manifests are unsupported.');
                        $manifest = $name;
                    }
                }
            }
            $this->check($manifest !== null, 'A SCORM manifest is required.');
            $xml = $zip->getFromName($manifest);
            $this->check(strlen($xml) < 2 * 1024 * 1024 && ! preg_match('/<!DOCTYPE|<!ENTITY/i', $xml), 'Unsafe manifest XML.');
            $previous = libxml_use_internal_errors(true);
            try {
                $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
            $this->check($doc !== false, 'Invalid manifest XML.');
            $resources = $doc->xpath('//*[local-name()="resources"]/*[local-name()="resource"][@href]');
            $items = $doc->xpath('//*[local-name()="organization"]/*[local-name()="item"][@identifierref]');
            $this->check(count($resources) > 0 && count($items) === 1, 'Upload one SCO per lesson. Multi-SCO sequencing is unsupported.');
            $ref = (string) $items[0]['identifierref'];
            $resource = null;
            foreach ($resources as $candidate) {
                if ((string) $candidate['identifier'] === $ref) {
                    $resource = $candidate;
                    break;
                }
            }
            $this->check($resource !== null, 'The launch resource is missing.');
            $href = (string) $resource['href'];
            $entryPoint = (dirname($manifest) === '.' ? '' : dirname($manifest).'/').$href;
            $this->check($this->safePath($entryPoint) && in_array(strtolower(pathinfo($entryPoint, PATHINFO_EXTENSION)), ['html', 'htm']) && $zip->locateName($entryPoint) !== false, 'Invalid launch resource.');
            $version = str_contains($xml, '2004') ? '2004' : '1.2';
            // Extract only validated regular files; never let ZipArchive resolve filesystem paths.
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (str_ends_with($name, '/')) {
                    continue;
                }
                $stream = $zip->getStream($name);
                $this->check($stream !== false, 'Unreadable archive entry.');
                try {
                    abort_unless(Storage::disk('local')->put($directory.'/'.$name, $stream), 503);
                } finally {
                    fclose($stream);
                }
            }

            return MediaAsset::create(['id' => $id, 'user_id' => $user->id, 'kind' => 'scorm', 'path' => $directory, 'mime' => 'application/zip',
                'bytes' => $total, 'sha256' => hash_file('sha256', $file->getRealPath()), 'entry_point' => $entryPoint, 'scorm_version' => $version, 'scanned_at' => $scanned]);
        } catch (\Throwable $e) {
            Storage::disk('local')->deleteDirectory($directory);
            throw $e;
        } finally {
            $zip->close();
        }
    }

    public function safePath(string $path): bool
    {
        if ($path === '' || strlen($path) > 240 || preg_match('/[\\\\:\x00-\x1f%?#]/', $path) || str_starts_with($path, '/')) {
            return false;
        }
        foreach (explode('/', rtrim($path, '/')) as $part) {
            if ($part === '' || $part === '.' || $part === '..' || str_starts_with($part, '.') || preg_match('/[. ]$/', $part) || preg_match('/^(con|prn|aux|nul|com[0-9]|lpt[0-9])(?:\.|$)/i', $part)) {
                return false;
            }
        }

        return true;
    }

    private function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['file' => $message]);
        }
    }
}
