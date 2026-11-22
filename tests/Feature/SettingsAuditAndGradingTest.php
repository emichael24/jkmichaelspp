<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Course;
use App\Models\MockExam;
use App\Models\Question;
use App\Models\User;
use App\Services\AssessmentAttemptService;
use App\Services\PlatformSettings;
use App\Services\QuestionGrader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SettingsAuditAndGradingTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_persist_close_registration_and_emit_redacted_append_only_audit(): void
    {
        Role::findOrCreate('platform_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('platform_admin');
        $this->actingAs($user);
        app(PlatformSettings::class)->put(['allow_registration' => false, 'platform_name' => 'JK Enterprise']);
        $this->assertSame(false, (new PlatformSettings)->get('allow_registration'));
        $this->assertSame('JK Enterprise', (new PlatformSettings)->get('platform_name'));
        $this->app['auth']->forgetGuards();
        $this->get('/register')->assertForbidden();
        $user->update(['password' => 'a-secret-that-must-not-be-logged', 'is_active' => false]);
        $log = AuditLog::where('auditable_type', User::class)->where('auditable_id', $user->id)->latest('id')->firstOrFail();
        $this->assertArrayNotHasKey('password', $log->new_values);
        $this->assertNotNull($log->correlation_id);
        $this->expectException(\LogicException::class);
        $log->update(['action' => 'tampered']);
    }

    public function test_grading_strategies_preserve_order_normalize_text_and_support_explicit_partial_credit(): void
    {
        $grader = app(QuestionGrader::class);
        $ordering = new Question(['type' => 'ordering', 'correct_answer_ids' => ['a', 'b', 'c']]);
        $this->assertSame(0.0, $grader->score($ordering, ['c', 'b', 'a']));
        $this->assertSame(1.0, $grader->score($ordering, ['a', 'b', 'c']));
        $blank = new Question(['type' => 'fill_blank', 'correct_answer_ids' => ['Project charter', 'Charter']]);
        $this->assertSame(1.0, $grader->score($blank, [' PROJECT   CHARTER ']));
        $formula = new Question(['type' => 'formula', 'options' => [], 'correct_answer_ids' => ['4.50'], 'grading_config' => ['tolerance' => 0.01]]);
        $this->assertSame(1.0, $grader->score($formula, ['4.501']));
        $this->assertSame(0.0, $grader->score($formula, ['4.6']));
        $matching = new Question(['type' => 'matching', 'correct_answer_ids' => ['a:y', 'b:x'], 'grading_config' => ['partial_credit' => true]]);
        $this->assertSame(0.5, $grader->score($matching, ['a:y']));
        $this->assertSame(0.0, $grader->score($matching, ['a:y', 'a:x']));
        $this->assertSame(1.0, $grader->score($matching, ['b:x', 'a:y']));
    }

    public function test_pause_preserves_remaining_time_and_start_resumes_a_single_attempt(): void
    {
        $this->travelTo(now()->startOfSecond());
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $question = Question::factory()->create(['course_id' => $course->id]);
        $exam = MockExam::factory()->create(['course_id' => $course->id, 'allow_pause' => true, 'time_limit_minutes' => 10]);
        $service = app(AssessmentAttemptService::class);
        $attempt = $service->start($exam, $user, fn () => collect([$question]));
        $this->assertSame($attempt->id, $service->start($exam, $user, fn () => collect([$question]))->id);
        $this->travel(2)->minutes();
        $service->pause($attempt);
        $remaining = $attempt->fresh()->remaining_seconds;
        $this->assertSame(480, $remaining);
        $this->travel(1)->hours();
        $resumed = $service->start($exam, $user, fn () => collect([$question]));
        $this->assertNull($resumed->paused_at);
        $this->assertEqualsWithDelta($remaining, now()->diffInSeconds($resumed->expires_at), 1);
    }
}
