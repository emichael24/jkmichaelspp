<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LearningReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_historical_completion_is_preserved_and_flagged_without_fabricating_a_certificate(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $course = Course::factory()->create(['is_published' => true]);
        $module = $course->modules()->create(['title' => 'Required work', 'is_published' => true]);
        $module->lessons()->create(['title' => 'Lesson', 'is_published' => true, 'content_type' => 'text']);
        $earned = now()->subYear()->startOfDay();
        $enrollment = $course->enrollments()->create(['user_id' => $user->id, 'status' => 'completed', 'enrolled_at' => $earned, 'completed_at' => $earned]);
        $this->artisan('learning:reconcile --apply')->assertFailed();
        $this->assertSame('completed', $enrollment->fresh()->status);
        $this->assertTrue($earned->equalTo($enrollment->fresh()->completed_at));
        $this->assertDatabaseCount('certificates', 0);
        $report = json_decode(Storage::disk('local')->get(Storage::disk('local')->files('operations')[0]), true);
        $this->assertCount(1, $report['historical_review_required']);
        $this->assertSame($enrollment->id, $report['historical_review_required'][0]['enrollment_id']);
    }

    public function test_reconcile_eligible_learning_is_explicit_and_idempotent(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $course = Course::factory()->create(['is_published' => true]);
        $module = $course->modules()->create(['title' => 'Required work', 'is_published' => true]);
        $lesson = $module->lessons()->create(['title' => 'Lesson', 'is_published' => true, 'content_type' => 'text']);
        $enrollment = $course->enrollments()->create(['user_id' => $user->id, 'status' => 'active', 'enrolled_at' => now()]);
        LessonProgress::create(['user_id' => $user->id, 'lesson_id' => $lesson->id, 'status' => 'completed', 'progress_pct' => 100, 'completed_at' => now()]);
        $this->artisan('learning:reconcile')->assertSuccessful();
        $this->assertSame('active', $enrollment->fresh()->status);
        $this->assertDatabaseCount('certificates', 0);
        $this->artisan('learning:reconcile --apply')->assertSuccessful();
        $earned = $enrollment->fresh()->completed_at;
        $this->travel(2)->days();
        $this->artisan('learning:reconcile --apply')->assertSuccessful();
        $this->assertSame('completed', $enrollment->fresh()->status);
        $this->assertTrue($earned->equalTo($enrollment->fresh()->completed_at));
        $this->assertDatabaseCount('certificates', 1);
    }
}
