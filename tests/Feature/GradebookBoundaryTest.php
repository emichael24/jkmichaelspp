<?php

namespace Tests\Feature;

use App\Models\Cohort;
use App\Models\Course;
use App\Models\Organization;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GradebookBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_gradebook_never_includes_foreign_course_attempts_or_stale_foreign_members(): void
    {
        $organization = Organization::create(['name' => 'Learning Company', 'contact_email' => 'learning@example.test', 'max_seats' => 10, 'is_active' => true]);
        $foreignOrganization = Organization::create(['name' => 'Other Company', 'contact_email' => 'other@example.test', 'max_seats' => 10, 'is_active' => true]);
        $teacher = User::factory()->create(['organization_id' => $organization->id]);
        $teacher->assignRole(Role::findOrCreate('facilitator', 'web'));
        $learner = User::factory()->create(['organization_id' => $organization->id]);
        $foreign = User::factory()->create(['organization_id' => $foreignOrganization->id]);
        $course = Course::factory()->create(['author_id' => $teacher->id]);
        $foreignCourse = Course::factory()->create();
        $cohort = Cohort::create(['course_id' => $course->id, 'organization_id' => $organization->id, 'facilitator_id' => $teacher->id, 'name' => 'Delivery', 'start_date' => now(), 'end_date' => now()->addMonth(), 'max_learners' => 10, 'status' => 'active']);
        foreach ([$learner, $foreign] as $member) {
            $cohort->members()->create(['user_id' => $member->id, 'role' => 'learner', 'status' => 'active', 'joined_at' => now()]);
        }
        $foreignQuiz = Quiz::create(['course_id' => $foreignCourse->id, 'title' => 'Other course assessment', 'type' => 'custom', 'question_count' => 1, 'is_published' => true]);
        QuizAttempt::create(['user_id' => $learner->id, 'quizzable_type' => Quiz::class, 'quizzable_id' => $foreignQuiz->id, 'total_count' => 1, 'started_at' => now()->subMinute(), 'completed_at' => now(), 'status' => 'completed', 'score_pct' => 99]);
        $this->actingAs($teacher)->get('/facilitator/cohorts/'.$cohort->id.'/gradebook')->assertInertia(fn (Assert $page) => $page->has('learners', 1)->where('learners.0.id', $learner->id)->where('learners.0.quiz_attempts', 0)->where('learners.0.quiz_avg_pct', null));
        $quiz = Quiz::create(['course_id' => $course->id, 'title' => 'Course assessment', 'type' => 'custom', 'question_count' => 1, 'is_published' => true]);
        foreach ([95, 76.5] as $score) {
            QuizAttempt::create(['user_id' => $learner->id, 'quizzable_type' => Quiz::class, 'quizzable_id' => $quiz->id, 'total_count' => 1, 'started_at' => now()->subMinute(), 'completed_at' => now(), 'status' => 'completed', 'score_pct' => $score]);
        }
        $this->get('/facilitator/cohorts/'.$cohort->id.'/gradebook')->assertInertia(fn (Assert $page) => $page->where('learners.0.quiz_attempts', 1)->where('learners.0.quiz_avg_pct', 76.5)->where('summary.average_quiz_score', 76.5));
        $outsider = User::factory()->create(['organization_id' => $foreignOrganization->id]);
        $outsider->assignRole('facilitator');
        $this->actingAs($outsider)->get('/facilitator/cohorts/'.$cohort->id.'/gradebook')->assertForbidden();
        $organization->update(['is_active' => false]);
        $this->actingAs($teacher)->getJson('/facilitator/cohorts/'.$cohort->id.'/gradebook')->assertForbidden();
    }
}
