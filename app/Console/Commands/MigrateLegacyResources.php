<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Resource;
use App\Models\User;
use App\Services\PrivateMediaService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MigrateLegacyResources extends Command
{
    protected $signature = 'media:migrate-legacy-resources {--apply : Scan, copy, verify and migrate eligible local resource files}';

    protected $description = 'Inventory legacy local course resources; preserve a private copy before removing public access';

    public function handle(PrivateMediaService $media): int
    {
        $failed = 0;
        $migrated = 0;
        $public = Storage::disk('public');
        $private = Storage::disk('local');
        foreach (Resource::with('course.author')->lazyById(100) as $resource) {
            $resource->refresh();
            if (preg_match('~/media/[a-f0-9-]{36}$~i', $resource->file_url ?? '')) {
                continue;
            }
            $path = $this->localPath($resource->file_url ?? '');
            $root = realpath($public->path(''));
            $source = $path ? realpath($public->path($path)) : false;
            if (! $root || ! $source || ! str_starts_with($source, $root.DIRECTORY_SEPARATOR) || ! is_file($source)) {
                $this->warn("Resource {$resource->id}: unavailable or external source; manual migration required.");
                $failed++;

                continue;
            }
            $this->line("Resource {$resource->id}: local file, ".filesize($source).' bytes.');
            if (! $this->option('apply')) {
                continue;
            }
            $owner = $resource->course?->author ?? User::role('platform_admin')->where('is_active', true)->first();
            if (! $owner) {
                $this->error("Resource {$resource->id}: an active content owner is required.");
                $failed++;

                continue;
            }
            try {
                $original = $resource->file_url;
                $hash = hash_file('sha256', $source);
                $asset = DB::transaction(function () use ($resource, $source, $owner, $media, $original, $hash, $private) {
                    $current = Resource::whereKey($resource->id)->lockForUpdate()->firstOrFail();
                    if ($current->file_url !== $original) {
                        throw new \RuntimeException('Resource changed during migration.');
                    }
                    $asset = $media->document(new UploadedFile($source, basename($source), null, null, true), $owner);
                    if (! hash_equals($hash, hash_file('sha256', $private->path($asset->path)))) {
                        throw new \RuntimeException('Private copy integrity check failed.');
                    }
                    foreach (Resource::where('file_url', $original)->orderBy('id')->lockForUpdate()->get() as $linked) {
                        $linked->update(['file_url' => $asset->url(), 'file_type' => $asset->mime, 'file_size_bytes' => $asset->bytes]);
                        AuditLog::log('legacy_resource_migration', Resource::class, $linked->id, null, ['asset_id' => $asset->id, 'sha256' => $hash]);
                    }

                    return $asset;
                }, 3);
                // The verified private asset is the retained recovery copy. Never remove first.
                if (! hash_equals($asset->sha256, hash_file('sha256', $source)) || ! $public->delete($path)) {
                    throw new \RuntimeException('Public copy could not be retired; investigate before release.');
                }
                $migrated++;
            } catch (\Throwable $error) {
                report($error);
                $this->error("Resource {$resource->id}: migration did not finish. Private copies are retained; inspect the protected application log.");
                $failed++;
            }
        }
        $this->info("Migrated: {$migrated}; unresolved: {$failed}. ".($this->option('apply') ? 'Recheck public storage before release.' : 'Read-only inventory; use --apply after review.'));

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function localPath(string $url): ?string
    {
        if (str_contains($url, '://')) {
            $base = parse_url(config('app.url'));
            $parts = parse_url($url);
            if (($parts['host'] ?? null) !== ($base['host'] ?? null) || isset($parts['user']) || isset($parts['pass']) || ! in_array($parts['scheme'] ?? '', ['https', 'http'])) {
                return null;
            }
            $url = $parts['path'] ?? '';
        }
        $path = preg_replace('~^/?storage/~', '', $url);
        if (! str_starts_with($path, 'courses/resources/') || str_contains($path, '..') || str_contains($path, '\\') || str_contains($path, '%') || str_contains($path, "\0")) {
            return null;
        }

        return $path;
    }
}
