<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class UploadRetentionService
{
    public function cleanup(int $limit = 1000): array
    {
        $disk = Storage::disk('local');
        $removed = 0;
        $bytes = 0;
        $visited = 0;
        foreach ($disk->directories('upload-parts') as $userDirectory) {
            if (! preg_match('~^upload-parts/[0-9]+$~', $userDirectory) || is_link($disk->path($userDirectory))) {
                continue;
            }
            foreach ($disk->directories($userDirectory) as $directory) {
                if (++$visited > $limit) {
                    break 2;
                }
                if (! preg_match('~^upload-parts/[0-9]+/[a-f0-9]{64}$~', $directory) || is_link($disk->path($directory)) || is_link($disk->path($directory.'/lock'))) {
                    continue;
                }
                $lock = fopen($disk->path($directory.'/lock'), 'c');
                if (! $lock) {
                    continue;
                }
                try {
                    if (! flock($lock, LOCK_EX | LOCK_NB)) {
                        continue;
                    }
                    $files = array_values(array_filter($disk->files($directory), fn ($file) => basename($file) !== 'lock'));
                    if (! $files || collect($files)->contains(fn ($file) => is_link($disk->path($file)) || $disk->lastModified($file) > now()->subDay()->timestamp)) {
                        continue;
                    }
                    foreach ($files as $file) {
                        $size = $disk->size($file);
                        if ($disk->delete($file)) {
                            $removed++;
                            $bytes += $size;
                        }
                    }
                    // Keep the lock inode: removing it could let a waiting upload bypass this lock.
                } finally {
                    flock($lock, LOCK_UN);
                    fclose($lock);
                }
            }
        }

        return ['files_removed' => $removed, 'bytes_reclaimed' => $bytes, 'directories_checked' => min($visited, $limit)];
    }
}
