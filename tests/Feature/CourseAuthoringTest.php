<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CourseAuthoringTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_facilitator_course_counts_and_filters_only_include_owned_courses(): void
    {
        $facilitator = $this->actor('facilitator');
        $draft = Course::factory()->create(['author_id' => $facilitator->id, 'approval_status' => 'draft']);
        Course::factory()->create(['author_id' => $facilitator->id, 'approval_status' => 'approved']);
        Course::factory()->create(['approval_status' => 'draft']);
        $draft->modules()->create(['title' => 'Module', 'sort_order' => 1]);
        $this->actingAs($facilitator)->get('/facilitator/courses?status=draft')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Facilitator/Courses/Index')
                ->where('currentStatus', 'draft')
                ->where('counts.draft', 1)
                ->where('counts.approved', 1)
                ->has('courses.data', 1)
                ->where('courses.data.0.id', $draft->id)
                ->where('courses.data.0.modules_count', 1));
    }

    public function test_admin_and_facilitator_can_create_free_courses_and_save_paid_course_details(): void
    {
        $category = Category::create(['name' => 'Professional skills', 'slug' => 'professional-skills', 'is_active' => true]);
        foreach (['admin' => 'platform_admin', 'facilitator' => 'facilitator'] as $workspace => $role) {
            $user = $this->actor($role);
            $payload = ['title' => "New {$workspace} course", 'category_id' => $category->id,
                'level' => 'beginner', 'type' => 'self_paced', 'price' => 0, 'duration_hours' => 2.5];
            $this->actingAs($user)->post("/{$workspace}/courses", $payload)->assertSessionHasNoErrors()->assertRedirect();
            $course = Course::where('author_id', $user->id)->firstOrFail();
            $this->assertFalse($course->is_published);
            $this->assertEquals(0, $course->price);
            $this->assertEquals(2.5, $course->duration_hours);
            $payload = array_merge($payload, ['type' => 'bootcamp', 'price' => 20000, 'discount_price' => 15000]);
            $this->put("/{$workspace}/courses/{$course->id}", $payload)->assertSessionHasNoErrors();
            $this->assertSame('bootcamp', $course->fresh()->type);
            $this->assertEquals(15000, $course->fresh()->discount_price);
            $this->put("/{$workspace}/courses/{$course->id}", array_merge($payload, ['discount_price' => 30000]))->assertSessionHasErrors('discount_price');
            $this->put("/{$workspace}/courses/{$course->id}", array_merge($payload, ['type' => 'premium', 'level' => 'expert']))->assertSessionHasErrors(['type', 'level']);
        }
    }

    public function test_facilitator_can_author_review_and_edit_all_lesson_fields_with_scoped_routes(): void
    {
        Notification::fake();
        $facilitator = $this->actor('facilitator');
        $course = Course::factory()->create(['author_id' => $facilitator->id, 'created_by_role' => 'facilitator', 'approval_status' => 'draft', 'is_published' => false]);
        $base = "/facilitator/courses/{$course->id}";
        $this->actingAs($facilitator)->post("{$base}/modules", ['title' => 'Getting started'])->assertSessionHasNoErrors();
        $module = $course->modules()->firstOrFail();
        $lessons = "{$base}/modules/{$module->id}/lessons";
        $this->get("{$lessons}/create")->assertOk();
        $document = MediaAsset::create(['id' => (string) Str::uuid(), 'user_id' => $facilitator->id, 'kind' => 'document', 'path' => 'test/slides.pdf', 'mime' => 'application/pdf', 'bytes' => 10, 'sha256' => str_repeat('a', 64)]);
        $payload = ['title' => 'Apply the framework', 'content_type' => 'mixed', 'text_content' => '<p>A worked example.</p>',
            'summary' => 'A practical exercise', 'slides_url' => $document->url(), 'video_transcript' => 'A transcript.',
            'key_terms' => [['term' => 'Scope', 'definition' => 'The agreed boundaries.']], 'duration_minutes' => 10,
            'is_published' => true, 'is_downloadable' => true, 'is_free_preview' => false, 'unlock_type' => 'days_after_enrollment', 'unlock_days_after_enrollment' => 2];
        $this->post($lessons, $payload)->assertSessionHasNoErrors()->assertRedirect("{$base}/edit");
        $lesson = $module->lessons()->firstOrFail();
        $this->assertSame($payload['slides_url'], $lesson->slides_url);
        $this->assertSame($payload['key_terms'], $lesson->key_terms);
        $this->get("{$lessons}/{$lesson->id}/edit")->assertOk();
        $this->put("{$lessons}/{$lesson->id}", array_merge($payload, ['title' => 'Revised lesson', 'unlock_type' => 'immediate']))->assertSessionHasNoErrors();
        $this->assertSame('Revised lesson', $lesson->fresh()->title);
        $this->assertNull($lesson->fresh()->unlock_days_after_enrollment);
        $this->post("{$base}/submit-review")->assertSessionHasNoErrors();
        $this->assertSame('pending_review', $course->fresh()->approval_status);
        $this->put("{$lessons}/{$lesson->id}", $payload)->assertForbidden();
        $this->actingAs($this->actor('facilitator'))->get("{$lessons}/{$lesson->id}/edit")->assertForbidden();
    }

    public function test_release_dates_are_required_and_facilitator_thumbnail_upload_is_scoped(): void
    {
        Storage::fake('public');
        $facilitator = $this->actor('facilitator');
        $course = Course::factory()->create(['author_id' => $facilitator->id, 'approval_status' => 'draft', 'is_published' => false]);
        $module = $course->modules()->create(['title' => 'Module', 'sort_order' => 1]);
        $this->actingAs($facilitator)->post("/facilitator/courses/{$course->id}/modules/{$module->id}/lessons", [
            'title' => 'Scheduled lesson', 'content_type' => 'text', 'unlock_type' => 'date',
        ])->assertSessionHasErrors('unlock_at');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jD6kAAAAASUVORK5CYII=');
        $this->post("/facilitator/courses/{$course->id}/thumbnail", ['thumbnail' => UploadedFile::fake()->createWithContent('course.png', $png)])->assertSessionHasNoErrors();
        Storage::disk('public')->assertExists($course->fresh()->thumbnail);
        $this->actingAs($this->actor('facilitator'))->post("/facilitator/courses/{$course->id}/thumbnail", ['thumbnail' => UploadedFile::fake()->createWithContent('other.png', $png)])->assertForbidden();
    }
}
