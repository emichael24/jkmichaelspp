<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Forum;
use App\Models\LessonProgress;
use App\Models\Organization;
use App\Models\Quiz;
use App\Models\StudyGroup;
use App\Models\User;
use App\Services\CommunityAccessService;
use App\Services\CourseCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantAndCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_community_queries_and_guessed_ids_do_not_cross_tenants(): void
    {
        $a = Organization::create(['name' => 'A', 'contact_email' => 'a@example.test']);
        $b = Organization::create(['name' => 'B', 'contact_email' => 'b@example.test']);
        $user = User::factory()->create(['organization_id' => $a->id]);
        $other = User::factory()->create(['organization_id' => $b->id]);
        $mine = Forum::create(['title' => 'A discussions', 'organization_id' => $a->id, 'is_general' => true]);
        $foreign = Forum::create(['title' => 'B confidential', 'organization_id' => $b->id, 'is_general' => true]);
        $group = StudyGroup::create(['name' => 'Private B', 'organization_id' => $b->id, 'created_by' => $other->id, 'is_public' => true]);
        $access = app(CommunityAccessService::class);
        $this->assertSame([$mine->id], $access->forums($user)->pluck('id')->all());
        $this->assertFalse($access->peers($user)->whereKey($other->id)->exists());
        $this->actingAs($user)->get('/forums/'.$foreign->id)->assertNotFound();
        $this->post('/study-groups/'.$group->id.'/join')->assertNotFound();
        $this->get('/study-groups/'.$group->id)->assertNotFound();
        $this->assertDatabaseMissing('study_group_members', ['study_group_id' => $group->id, 'user_id' => $user->id]);
    }

    public function test_completion_requires_quizzes_is_monotonic_and_issues_one_pdf_certificate(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create(['is_published' => true]);
        $module = $course->modules()->create(['title' => 'Published', 'is_published' => true]);
        $lesson = $module->lessons()->create(['title' => 'Lesson', 'is_published' => true, 'content_type' => 'text']);
        $draft = $course->modules()->create(['title' => 'Draft', 'is_published' => false]);
        $draft->lessons()->create(['title' => 'Hidden', 'is_published' => true, 'content_type' => 'text']);
        $enrollment = $course->enrollments()->create(['user_id' => $user->id, 'status' => 'active', 'enrolled_at' => now()]);
        $quiz = Quiz::create(['title' => 'Required check', 'course_id' => $course->id, 'type' => 'lesson_check', 'passing_score_pct' => 70, 'question_count' => 1, 'is_published' => true]);
        $service = app(CourseCompletionService::class);
        $service->progress($user, $lesson, [], true);
        $this->assertSame('active', $enrollment->fresh()->status);
        $this->assertDatabaseCount('certificates', 0);
        $quiz->attempts()->create(['user_id' => $user->id, 'status' => 'completed', 'score_pct' => 70, 'started_at' => now(), 'completed_at' => now(), 'total_count' => 1]);
        $service->reconcile($user, $course);
        $completedAt = $enrollment->fresh()->completed_at->toISOString();
        $this->travel(1)->hours();
        $service->progress($user, $lesson, ['progress_pct' => 3, 'time_spent_seconds' => 1]);
        $service->reconcile($user, $course);
        $this->assertSame('completed', $enrollment->fresh()->status);
        $this->assertSame($completedAt, $enrollment->fresh()->completed_at->toISOString());
        $this->assertSame('completed', LessonProgress::first()->status);
        $this->assertDatabaseCount('certificates', 1);
        $certificate = Certificate::first();
        $response = $this->actingAs($user)->get('/certificates/'.$certificate->uuid.'/download')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $certificate->update(['is_revoked' => true]);
        $service->reconcile($user, $course);
        $this->assertDatabaseCount('certificates', 1);
        $this->get('/certificates/'.$certificate->uuid.'/download')->assertForbidden();
    }
}
