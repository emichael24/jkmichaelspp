<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\MediaAsset;
use App\Models\User;
use App\Services\UploadRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_resource_is_scanned_copied_verified_and_shared_references_preserved(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        config(['media.require_scan' => false]);
        $course = Course::factory()->create(['author_id' => User::factory()->create()->id]);
        $path = 'courses/resources/workbook.txt';
        Storage::disk('public')->put($path, 'Private original workbook');
        $first = $course->resources()->create(['title' => 'Workbook', 'type' => 'template', 'file_url' => $path]);
        $second = $course->resources()->create(['title' => 'Workbook copy', 'type' => 'template', 'file_url' => $path]);
        $this->artisan('media:migrate-legacy-resources')->assertSuccessful();
        Storage::disk('public')->assertExists($path);
        $this->assertDatabaseCount('media_assets', 0);
        $this->artisan('media:migrate-legacy-resources --apply')->assertSuccessful();
        $asset = MediaAsset::firstOrFail();
        $this->assertSame(hash('sha256', 'Private original workbook'), $asset->sha256);
        $this->assertSame('Private original workbook', Storage::disk('local')->get($asset->path));
        $this->assertSame($asset->url(), $first->fresh()->file_url);
        $this->assertSame($asset->url(), $second->fresh()->file_url);
        Storage::disk('public')->assertMissing($path);
        $this->artisan('media:migrate-legacy-resources --apply')->assertSuccessful();
        $this->assertDatabaseCount('media_assets', 1);
    }

    public function test_failed_scan_and_unsafe_legacy_references_keep_the_original(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        config(['media.require_scan' => true, 'media.scanner' => '/nonexistent/scanner']);
        $course = Course::factory()->create(['author_id' => User::factory()->create()->id]);
        $path = 'courses/resources/workbook.txt';
        Storage::disk('public')->put($path, 'Private original workbook');
        $resource = $course->resources()->create(['title' => 'Workbook', 'type' => 'template', 'file_url' => $path]);
        $this->artisan('media:migrate-legacy-resources --apply')->assertFailed();
        Storage::disk('public')->assertExists($path);
        $this->assertSame($path, $resource->fresh()->file_url);
        $resource->update(['file_url' => '../private/secrets.txt']);
        $this->artisan('media:migrate-legacy-resources --apply')->assertFailed();
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_retention_preserves_current_locked_and_unrelated_files(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');
        $expired = 'upload-parts/1/'.str_repeat('a', 64);
        $active = 'upload-parts/1/'.str_repeat('b', 64);
        $locked = 'upload-parts/1/'.str_repeat('c', 64);
        foreach ([$expired, $active, $locked] as $directory) {
            $disk->put($directory.'/0.part', 'private bytes');
            $disk->put($directory.'/state.json', '{}');
            if ($directory !== $active) {
                touch($disk->path($directory.'/0.part'), now()->subDays(2)->timestamp);
                touch($disk->path($directory.'/state.json'), now()->subDays(2)->timestamp);
            }
        }
        $disk->put('media/retained/document', 'retained content');
        $lock = fopen($disk->path($locked.'/lock'), 'c');
        flock($lock, LOCK_EX);
        try {
            $result = app(UploadRetentionService::class)->cleanup();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        $this->assertSame(2, $result['files_removed']);
        $disk->assertMissing($expired.'/0.part');
        foreach ([$active.'/0.part', $locked.'/0.part', 'media/retained/document', $expired.'/lock'] as $file) {
            $disk->assertExists($file);
        }
    }
}
