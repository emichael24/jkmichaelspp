<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\MockExam;
use App\Models\Organization;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\ScormProgress;
use App\Models\Simulation;
use App\Models\SimulationAttempt;
use App\Models\User;
use App\Models\UserGamification;
use App\Services\AssessmentAttemptService;
use App\Services\AssessmentBlueprintService;
use App\Services\GamificationService;
use App\Services\QuestionImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LearningIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_exam_grades_only_saved_answers_and_repeated_submit_is_idempotent(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $course->enrollments()->create(['user_id' => $user->id, 'status' => 'active', 'enrolled_at' => now()]);
        $question = Question::factory()->create(['course_id' => $course->id]);
        $exam = MockExam::factory()->create(['course_id' => $course->id, 'question_count' => 1, 'time_limit_minutes' => 1, 'domain_distribution' => []]);
        $service = app(AssessmentAttemptService::class);
        $attempt = $service->start($exam, $user, fn () => collect([$question]));
        $service->saveDraft($attempt, [$question->id => ['B']]);
        $this->travel(2)->minutes();
        $result = $service->complete($attempt, [['question_id' => $question->id, 'selected_ids' => ['A']]]);
        $this->assertEquals(0, $result->score_pct);
        $this->assertSame(['B'], $result->responses()->first()->selected_answer_ids);
        $at = $result->completed_at->toISOString();
        $this->travel(1)->minutes();
        $result = $service->complete($attempt, [['question_id' => $question->id, 'selected_ids' => ['A']]]);
        $this->assertSame($at, $result->completed_at->toISOString());
        $this->assertDatabaseCount('gamification_events', 1);
        $this->assertDatabaseCount('question_responses', 1);
    }

    public function test_rewards_are_unique_per_source_event_and_day(): void
    {
        $user = User::factory()->create();
        $rewards = app(GamificationService::class);
        $rewards->awardXp($user->id, 'daily_login');
        $rewards->awardXp($user->id, 'daily_login');
        $rewards->awardXp($user->id, 'lesson_complete', ['event_key' => 'lesson:1']);
        $rewards->awardXp($user->id, 'lesson_complete', ['event_key' => 'lesson:1']);
        $this->assertDatabaseCount('gamification_events', 2);
        $this->assertSame(15, UserGamification::forUser($user->id)->total_xp);
        $this->travel(1)->days();
        $rewards->awardXp($user->id, 'daily_login');
        $this->assertSame(20, UserGamification::forUser($user->id)->total_xp);
    }

    public function test_inactive_organization_terminates_existing_learner_sessions(): void
    {
        $organization = Organization::create(['name' => 'Suspended organization', 'contact_email' => 'admin@example.test', 'is_active' => true]);
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $organization->update(['is_active' => false]);
        $this->actingAs($user)->get('/dashboard')->assertForbidden();
        $this->assertGuest();
    }

    public function test_question_import_validates_keys_and_stores_structured_drafts(): void
    {
        $course = Course::factory()->create(['is_published' => false]);
        $file = tempnam(sys_get_temp_dir(), 'jk-import-');
        $handle = fopen($file, 'w');
        fputcsv($handle, ['domain', 'type', 'difficulty', 'stem', 'option_1', 'option_2', 'option_3', 'option_4', 'correct_options', 'explanation']);
        fputcsv($handle, ['Scope', 'multiple_choice', 'easy', 'Valid question?', 'One', 'Two', '', '', '1', 'One applies.']);
        fputcsv($handle, ['Scope', 'multiple_choice', 'easy', 'Invalid key?', 'One', 'Two', '', '', '9', 'Unavailable.']);
        fputcsv($handle, ['Too few columns']);
        fclose($handle);
        try {
            $result = app(QuestionImportService::class)->importCsv($file, $course->id);
        } finally {
            unlink($file);
        }
        $this->assertSame(1, $result['success_count']);
        $this->assertCount(2, $result['errors']);
        $question = Question::firstOrFail();
        $this->assertSame('draft', $question->status);
        $this->assertSame(['1'], $question->correct_answer_ids);
        $this->assertSame('One', $question->options[0]['text']);
    }

    public function test_simulation_requires_enrollment_end_node_and_owner_review(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $course = Course::factory()->create();
        $simulation = Simulation::create(['course_id' => $course->id, 'title' => 'Decision path', 'context_narrative' => 'Synthetic case study.', 'type' => 'branching_scenario', 'is_published' => true]);
        $start = $simulation->nodes()->create(['type' => 'decision', 'content' => 'Choose a path', 'is_start' => true]);
        $end = $simulation->nodes()->create(['type' => 'end', 'content' => 'Finished']);
        $choice = $start->choices()->create(['label' => 'Proceed', 'next_node_id' => $end->id, 'score_impact' => ['judgment' => 5]]);
        $this->actingAs($user)->post(route('simulations.start', $simulation))->assertForbidden();
        $course->enrollments()->create(['user_id' => $user->id, 'status' => 'active', 'enrolled_at' => now()]);
        $this->post(route('simulations.start', $simulation))->assertOk();
        $attempt = SimulationAttempt::firstOrFail();
        $this->postJson(route('simulations.complete', $attempt), ['time_spent_seconds' => 20])->assertUnprocessable();
        $this->postJson(route('simulations.choose', $attempt), ['node_id' => $start->id, 'choice_id' => $choice->id])->assertOk();
        $this->postJson(route('simulations.choose', $attempt), ['node_id' => $start->id, 'choice_id' => $choice->id])->assertConflict();
        $this->postJson(route('simulations.complete', $attempt), ['time_spent_seconds' => 20])->assertOk();
        $this->postJson(route('simulations.complete', $attempt), ['time_spent_seconds' => 90])->assertOk();
        $this->assertEquals(20, $attempt->fresh()->time_spent_seconds);
        $this->assertDatabaseCount('gamification_events', 1);
        $this->actingAs($other)->get(route('simulations.report', $attempt))->assertForbidden();
    }

    public function test_blueprint_satisfies_intersecting_quotas_and_never_relaxes_them(): void
    {
        $course = Course::factory()->create();
        foreach ([['A', 'easy'], ['A', 'hard'], ['B', 'easy']] as [$domain, $difficulty]) {
            Question::factory()->create(['course_id' => $course->id, 'domain' => $domain, 'difficulty' => $difficulty, 'status' => 'published']);
        }
        $service = app(AssessmentBlueprintService::class);
        $selected = $service->select($course->id, 2, ['A' => 1, 'B' => 1], ['easy' => 1, 'hard' => 1]);
        $this->assertEqualsCanonicalizing(['A/hard', 'B/easy'], $selected->map(fn ($q) => $q->domain.'/'.$q->difficulty)->all());
        $this->assertSame(['A' => 3, 'B' => 2], $service->counts(['A' => 50, 'B' => 50], 5, 'domain_distribution'));
        $this->expectException(ValidationException::class);
        $service->select($course->id, 2, ['B' => 2], ['hard' => 2]);
    }

    public function test_learner_discovery_hides_drafts_unpublished_modules_and_unreleased_actions(): void
    {
        $user = User::factory()->create();
        $draft = Course::factory()->create(['is_published' => false]);
        $course = Course::factory()->create(['is_published' => true]);
        foreach ([$draft, $course] as $item) {
            $item->enrollments()->create(['user_id' => $user->id, 'status' => 'active', 'enrolled_at' => now()]);
        }
        $hidden = $course->modules()->create(['title' => 'Hidden module', 'is_published' => false]);
        $hidden->lessons()->create(['title' => 'Hidden lesson', 'is_published' => true]);
        $module = $course->modules()->create(['title' => 'Visible', 'is_published' => true]);
        $module->lessons()->create(['title' => 'Later', 'is_published' => true, 'unlock_type' => 'date', 'unlock_at' => now()->addDay()]);
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')->has('courses', 1)->where('courses.0.id', $course->id)
            ->where('courses.0.lessons_count', 1)->where('stats.activeCourses', 1)
            ->where('continueCourse.currentLesson', 'Waiting for the next lesson release')
            ->where('todayPlan', fn ($items) => collect($items)->where('type', 'lesson')->isEmpty()));
    }

    public function test_stale_scorm_commits_preserve_earned_status_score_and_timestamp(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create(['is_published' => true]);
        $course->enrollments()->create(['user_id' => $user->id, 'status' => 'active', 'enrolled_at' => now()]);
        $module = $course->modules()->create(['title' => 'SCORM', 'is_published' => true]);
        $lesson = $module->lessons()->create(['title' => 'SCO', 'is_published' => true, 'scorm_path' => 'private-package']);
        $url = route('scorm.commit', $lesson->id);
        $this->actingAs($user)->postJson($url, ['cmi_core_lesson_status' => 'passed', 'cmi_core_score_raw' => 90])->assertOk();
        $at = $lesson->progress()->first()->completed_at->toISOString();
        $this->travel(1)->hours();
        $this->postJson($url, ['cmi_core_lesson_status' => 'incomplete', 'cmi_core_score_raw' => 10])->assertOk()->assertJsonPath('completed', true);
        $this->assertSame('passed', ScormProgress::first()->cmi_core_lesson_status);
        $this->assertEquals(90, ScormProgress::first()->cmi_core_score_raw);
        $this->assertSame($at, $lesson->progress()->first()->completed_at->toISOString());
        $this->assertDatabaseCount('certificates', 1);
    }

    public function test_partial_credit_is_consistent_between_overall_and_domain_scores(): void
    {
        $user = User::factory()->create();
        $question = Question::factory()->create(['domain' => 'Application']);
        $quiz = Quiz::create(['course_id' => $question->course_id, 'title' => 'Check', 'type' => 'custom', 'question_count' => 2]);
        $attempt = $quiz->attempts()->create(['user_id' => $user->id, 'status' => 'in_progress', 'started_at' => now(), 'total_count' => 2,
            'question_snapshot' => [$question->attributesToArray(), array_merge($question->attributesToArray(), ['id' => $question->id + 1])]]);
        $attempt->responses()->create(['user_id' => $user->id, 'question_id' => $question->id, 'selected_answer_ids' => ['a'], 'is_correct' => false, 'score_fraction' => .5, 'time_spent_seconds' => 5]);
        $attempt->calculateScore();
        $this->assertEquals(25, $attempt->score_pct);
        $this->assertEquals(25, $attempt->domain_scores['Application']['pct']);
        $this->assertEquals(.5, $attempt->getReview()[0]['score_fraction']);
    }
}
