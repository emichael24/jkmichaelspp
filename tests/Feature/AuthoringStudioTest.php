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

class AuthoringStudioTest extends TestCase
{
    use RefreshDatabase;

    private function author(string $role = 'facilitator'): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function draft(User $author): Course
    {
        return Course::factory()->create(['author_id' => $author->id, 'is_published' => false, 'published_at' => null, 'approval_status' => 'draft']);
    }

    public function test_both_workspaces_save_rich_lessons_and_remain_in_the_editor(): void
    {
        foreach (['admin' => 'platform_admin', 'facilitator' => 'facilitator'] as $workspace => $role) {
            $author = $this->author($role);
            $course = $this->draft($author);
            $module = $course->modules()->create(['title' => 'Foundations', 'is_published' => true]);
            $base = $workspace === 'admin' ? "/admin/modules/{$module->id}/lessons" : "/facilitator/courses/{$course->id}/modules/{$module->id}/lessons";
            $html = '<h2>Plan the work</h2><p><strong>Clear goals</strong> matter.</p><table><tbody><tr><th>Goal</th><th>Owner</th></tr></tbody></table>';
            $payload = ['title' => 'Build a project charter', 'content_type' => 'text', 'text_content' => $html, 'is_published' => false, 'stay_in_editor' => true];
            $this->actingAs($author)->post($base, $payload)->assertSessionHasNoErrors();
            $lesson = $module->lessons()->firstOrFail();
            $edit = $workspace === 'admin' ? "/admin/lessons/{$lesson->id}/edit" : "{$base}/{$lesson->id}/edit";
            $this->assertSame($html, $lesson->text_content);
            $this->assertFalse($lesson->is_published);
            $this->get($edit)->assertOk();
            $this->put(substr($edit, 0, -5), [...$payload, 'title' => 'Refined charter'])->assertRedirect($edit)->assertSessionHasNoErrors();
            $this->assertSame('Refined charter', $lesson->fresh()->title);
        }
    }

    public function test_private_pdf_and_image_uploads_can_be_attached_but_not_stolen(): void
    {
        Storage::fake('local');
        config(['media.require_scan' => false]);
        $author = $this->author();
        $course = $this->draft($author);
        $module = $course->modules()->create(['title' => 'Documents', 'is_published' => true]);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jD6kAAAAASUVORK5CYII=');
        $this->actingAs($author);
        $image = $this->postJson('/authoring/media/image', ['course_id' => $course->id, 'file' => UploadedFile::fake()->createWithContent('diagram.png', $png)])->assertOk()->json('url');
        $pdf = $this->postJson('/authoring/media/pdf', ['course_id' => $course->id, 'file' => UploadedFile::fake()->createWithContent('guide.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF")])->assertOk()->json('url');
        $this->post("/facilitator/courses/{$course->id}/modules/{$module->id}/lessons", ['title' => 'Read the guide', 'content_type' => 'mixed', 'pdf_url' => $pdf, 'text_content' => '<p>Reference</p><img src="'.$image.'" alt="Project diagram">', 'is_published' => true, 'is_downloadable' => true])->assertSessionHasNoErrors();
        $this->get($pdf)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get($image)->assertOk()->assertHeader('Content-Type', 'image/png');
        $foreign = $this->author();
        $foreignCourse = $this->draft($foreign);
        $foreignModule = $foreignCourse->modules()->create(['title' => 'Other']);
        $this->actingAs($foreign)->get($image)->assertForbidden();
        $this->get($pdf)->assertForbidden();
        $this->postJson('/authoring/media/image', ['course_id' => $course->id, 'file' => UploadedFile::fake()->createWithContent('diagram.png', $png)])->assertForbidden();
        $this->post("/facilitator/courses/{$foreignCourse->id}/modules/{$foreignModule->id}/lessons", ['title' => 'Attempted reuse', 'content_type' => 'text', 'text_content' => '<img src="'.$image.'">'])->assertForbidden();
        $course->update(['is_published' => true, 'approval_status' => 'approved']);
        $learner = User::factory()->create();
        $enrollment = $course->enrollments()->create(['user_id' => $learner->id, 'status' => 'active', 'enrolled_at' => now()]);
        $this->actingAs($learner)->get($image)->assertOk();
        $this->get($pdf)->assertOk();
        $lesson = $module->lessons()->firstOrFail();
        $this->get("/lessons/{$lesson->id}/download/pdf")->assertRedirect($pdf.'?download=1');
        $this->get($pdf.'?download=1')->assertOk()->assertDownload('resource.pdf');
        $enrollment->update(['status' => 'suspended']);
        $this->get($image)->assertForbidden();
    }

    public function test_upload_rejects_spoofed_files_and_locked_courses(): void
    {
        Storage::fake('local');
        config(['media.require_scan' => false]);
        $author = $this->author();
        $course = $this->draft($author);
        $this->actingAs($author)->postJson('/authoring/media/image', ['course_id' => $course->id,
            'file' => UploadedFile::fake()->createWithContent('diagram.png', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')])->assertUnprocessable();
        $this->assertSame(0, MediaAsset::count());
        $course->update(['approval_status' => 'pending_review']);
        $this->postJson('/authoring/media/pdf', ['course_id' => $course->id, 'file' => UploadedFile::fake()->createWithContent('guide.pdf', '%PDF-1.4')])->assertForbidden();
    }

    public function test_curriculum_reordering_is_complete_scoped_and_persistent(): void
    {
        $author = $this->author();
        $course = $this->draft($author);
        $first = $course->modules()->create(['title' => 'First', 'sort_order' => 1]);
        $second = $course->modules()->create(['title' => 'Second', 'sort_order' => 2]);
        $a = $first->lessons()->create(['title' => 'A', 'content_type' => 'text', 'sort_order' => 1]);
        $b = $first->lessons()->create(['title' => 'B', 'content_type' => 'text', 'sort_order' => 2]);
        $url = "/authoring/courses/{$course->id}/reorder";
        $this->actingAs($author)->post($url, ['order' => [$second->id, $first->id]])->assertSessionHasNoErrors();
        $this->assertSame([$second->id, $first->id], $course->modules()->pluck('id')->all());
        $this->post($url, ['module_id' => $first->id, 'order' => [$b->id, $a->id]])->assertSessionHasNoErrors();
        $this->assertSame([$b->id, $a->id], $first->lessons()->pluck('id')->all());
        $this->postJson($url, ['order' => [$first->id]])->assertUnprocessable();
        $this->postJson($url, ['module_id' => $second->id, 'order' => [$a->id, $b->id]])->assertUnprocessable();
        $this->actingAs($this->author())->postJson($url, ['order' => [$first->id, $second->id]])->assertForbidden();
    }

    public function test_commerce_bookmark_redirects_to_a_working_sqlite_billing_page(): void
    {
        $this->actingAs($this->author('platform_admin'))->get('/admin/commerce')->assertRedirect('/admin/billing');
        $this->get('/admin/billing')->assertOk();
    }
}
