<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use ZipArchive;

class PrivateMediaTest extends TestCase
{
    use RefreshDatabase;

    private function author(): User
    {
        Storage::fake('local');
        Storage::fake('public');
        config(['media.require_scan' => false]);
        Role::findOrCreate('platform_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('platform_admin');

        return $user;
    }

    private function archive(array $extra = []): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'scorm');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $files = array_merge([
            'imsmanifest.xml' => '<manifest><metadata><schemaversion>1.2</schemaversion></metadata><organizations><organization><item identifierref="sco"/></organization></organizations><resources><resource identifier="sco" href="index.html"/></resources></manifest>',
            'index.html' => '<!doctype html><html><body><button onclick="API.LMSSetValue(\'cmi.core.lesson_status\',\'completed\');API.LMSCommit(\'\')">Finish</button></body></html>',
        ], $extra);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return new UploadedFile($path, 'lesson.zip', 'application/zip', null, true);
    }

    public function test_archive_rejects_traversal_and_server_scripts_before_extracting(): void
    {
        $this->actingAs($this->author());
        foreach (['../escape.html', '/absolute.html', 'folder\\escape.html', 'shell.php', 'test%2e.html'] as $name) {
            $this->postJson('/admin/media/scorm', ['file' => $this->archive([$name => 'unsafe'])])->assertUnprocessable();
        }
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_scorm_is_private_sandboxed_and_revokes_existing_launch_on_suspension(): void
    {
        $author = $this->author();
        $this->actingAs($author)->postJson('/admin/media/scorm', ['file' => $this->archive()])->assertOk();
        $asset = MediaAsset::firstOrFail();
        $course = Course::factory()->create(['is_published' => true]);
        $module = $course->modules()->create(['title' => 'Module', 'is_published' => true]);
        $lesson = $module->lessons()->create(['title' => 'Activity', 'is_published' => true, 'content_type' => 'mixed', 'scorm_path' => $asset->url()]);
        $learner = User::factory()->create();
        $this->actingAs($learner)->getJson('/lessons/'.$lesson->id.'/scorm-launch')->assertForbidden();
        $enrollment = $course->enrollments()->create(['user_id' => $learner->id, 'status' => 'active', 'enrolled_at' => now()]);
        $launch = $this->getJson('/lessons/'.$lesson->id.'/scorm-launch')->assertOk();
        $html = $this->get($launch->json('url'))->assertOk();
        $this->assertStringContainsString('sandbox allow-scripts;', $html->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString('allow-same-origin', $html->headers->get('Content-Security-Policy'));
        $html->assertSee('jk-scorm-commit');
        $enrollment->update(['status' => 'suspended']);
        $this->get($launch->json('url'))->assertForbidden();
    }

    public function test_binary_chunks_are_ordered_idempotent_and_not_public(): void
    {
        $this->actingAs($this->author());
        $first = hex2bin('00000018667479706d703432000000006d70343269736f6d');
        $second = str_repeat("\0", 128);
        $post = fn ($i, $bytes) => $this->postJson('/admin/media/video', ['fileName' => 'upload-unique.mp4', 'chunkIndex' => $i, 'totalChunks' => 2, 'file' => UploadedFile::fake()->createWithContent('chunk', $bytes)]);
        $post(1, $second)->assertOk()->assertJsonPath('status', 'uploading');
        $post(1, $second)->assertOk();
        $post(1, 'changed')->assertConflict();
        $response = $post(0, $first)->assertOk()->assertJsonPath('status', 'success');
        $post(0, $first)->assertOk()->assertJsonPath('video_url', $response->json('video_url'));
        $asset = MediaAsset::firstOrFail();
        $this->assertSame(hash('sha256', $first.$second), $asset->sha256);
        $this->assertDatabaseCount('media_assets', 1);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->actingAs(User::factory()->create())->get($asset->url())->assertForbidden();
    }
}
