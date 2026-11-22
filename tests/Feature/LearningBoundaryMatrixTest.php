<?php

namespace Tests\Feature;

use App\Models\AiChatSession;
use App\Models\Course;
use App\Models\MediaAsset;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LearningBoundaryMatrixTest extends TestCase
{
    use RefreshDatabase;

    private function fixtures(): array
    {
        Storage::fake('local');
        config(['media.require_scan' => false]);
        $one = Organization::create(['name' => 'Organization One', 'contact_email' => 'one@example.test', 'max_seats' => 10]);
        $two = Organization::create(['name' => 'Organization Two', 'contact_email' => 'two@example.test', 'max_seats' => 10]);
        $learner = User::factory()->create(['organization_id' => $one->id]);
        $outsider = User::factory()->create(['organization_id' => $two->id]);
        $course = Course::factory()->create(['is_published' => true]);
        $module = $course->modules()->create(['title' => 'Published', 'is_published' => true]);
        $lesson = $module->lessons()->create(['title' => 'Protected reading', 'is_published' => true, 'content_type' => 'text', 'text_content' => '<p>PRIVATE LESSON EVIDENCE</p>']);
        $enrollment = $course->enrollments()->create(['user_id' => $learner->id, 'status' => 'active', 'enrolled_at' => now()]);
        $asset = MediaAsset::create(['id' => (string) Str::uuid(), 'user_id' => User::factory()->create()->id, 'kind' => 'document', 'path' => 'matrix/document', 'mime' => 'application/pdf', 'bytes' => 15, 'sha256' => hash('sha256', 'private content')]);
        Storage::disk('local')->put($asset->path, 'private content');
        $resource = $course->resources()->create(['title' => 'Course workbook', 'type' => 'template', 'file_url' => $asset->url(), 'is_published' => true]);

        return compact('one', 'two', 'learner', 'outsider', 'course', 'module', 'lesson', 'enrollment', 'asset', 'resource');
    }

    public function test_resource_download_and_direct_asset_require_the_same_live_entitlement(): void
    {
        extract($this->fixtures());
        $url = "/courses/{$course->slug}/resources/{$resource->id}/download";
        $this->actingAs($outsider)->get($url)->assertForbidden();
        $this->get($asset->url().'?download=1')->assertForbidden();
        $this->actingAs($learner)->get($url)->assertRedirect($asset->url().'?download=1');
        $download = $this->get($asset->url().'?download=1')->assertOk();
        $this->assertStringContainsString('no-store', $download->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $download->headers->get('Cache-Control'));
        $resource->update(['is_published' => false]);
        $this->get($url)->assertNotFound();
        $this->get($asset->url().'?download=1')->assertForbidden();
        $resource->update(['is_published' => true]);
        $course->update(['is_published' => false]);
        $this->get($url)->assertNotFound();
        $this->get($asset->url().'?download=1')->assertForbidden();
        $course->update(['is_published' => true]);
        $enrollment->update(['status' => 'suspended']);
        $this->get($url)->assertForbidden();
        $this->get($asset->url().'?download=1')->assertForbidden();
    }

    public function test_resources_fail_closed_for_unscanned_legacy_and_wrong_parent_files(): void
    {
        extract($this->fixtures());
        $this->actingAs($learner);
        $other = Course::factory()->create(['is_published' => true]);
        $this->get("/courses/{$other->slug}/resources/{$resource->id}/download")->assertNotFound();
        config(['media.require_scan' => true]);
        $this->get($asset->url())->assertStatus(423);
        $asset->update(['scanned_at' => now()]);
        $this->get($asset->url())->assertOk();
        foreach (['javascript:alert(1)', '/storage/legacy-workbook.pdf', 'https://uncontrolled.example.test/private.pdf'] as $url) {
            $resource->update(['file_url' => $url]);
            $this->get("/courses/{$course->slug}/resources/{$resource->id}/download")->assertConflict();
        }
    }

    public function test_catalog_payload_has_no_file_urls_or_hidden_curriculum_bodies(): void
    {
        extract($this->fixtures());
        $hidden = $course->modules()->create(['title' => 'Unpublished module', 'is_published' => false]);
        $hidden->lessons()->create(['title' => 'Hidden lesson', 'content_type' => 'text', 'text_content' => 'HIDDEN BODY', 'is_published' => true]);
        $this->actingAs($learner)->get('/courses/'.$course->slug)->assertInertia(fn (Assert $page) => $page
            ->where('course.modules_count', 1)->where('course.lessons_count', 1)->missing('course.lessons')
            ->missing('resources.0.file_url')->missing('course.resources.0.file_url')->missing('modules.0.lessons.0.text_content'));
    }

    public function test_saved_ai_conversation_rechecks_context_on_read_and_account_revocation(): void
    {
        extract($this->fixtures());
        $session = AiChatSession::create(['uuid' => (string) Str::uuid(), 'user_id' => $learner->id, 'title' => 'Lesson discussion', 'context_type' => 'lesson', 'context_id' => $lesson->id, 'messages' => [['role' => 'assistant', 'content' => 'Protected summary']]]);
        $url = '/ai/tutor/sessions/'.$session->id;
        $this->actingAs($outsider)->getJson($url)->assertForbidden();
        $this->actingAs($learner)->getJson($url)->assertOk();
        $enrollment->update(['status' => 'suspended']);
        $this->getJson($url)->assertForbidden();
        $enrollment->update(['status' => 'active']);
        $one->update(['is_active' => false]);
        $this->getJson($url)->assertForbidden();
    }

    public function test_bearer_scorm_url_checks_organization_even_without_browser_session(): void
    {
        extract($this->fixtures());
        $asset->update(['kind' => 'scorm', 'path' => 'matrix/package', 'entry_point' => 'index.html']);
        Storage::disk('local')->put('matrix/package/index.html', '<html>Package</html>');
        $lesson->update(['scorm_path' => $asset->url()]);
        $token = Crypt::encryptString(json_encode(['asset' => $asset->id, 'user' => $learner->id, 'lesson' => $lesson->id, 'expires' => time() + 60]));
        Auth::logout();
        $url = '/scorm-assets/'.rawurlencode($token).'/index.html';
        $this->get($url)->assertOk();
        $one->update(['is_active' => false]);
        $this->get($url)->assertForbidden();
        $one->update(['is_active' => true]);
        $learner->update(['is_active' => false]);
        $this->get($url)->assertNotFound();
    }
}
