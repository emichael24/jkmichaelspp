<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Cohort;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Meeting;
use App\Models\MockExam;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EnterpriseReadinessTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function lesson(Course $course, array $attributes = []): Lesson
    {
        $module = $course->modules()->create(['title' => 'Getting started', 'is_published' => true, 'sort_order' => 1]);

        return $module->lessons()->create(array_merge(['title' => 'First lesson', 'content_type' => 'text',
            'text_content' => 'A meaningful worked example.', 'is_published' => true, 'sort_order' => 1, 'is_downloadable' => true], $attributes));
    }

    private function enroll(User $user, Course $course): void
    {
        $course->enrollments()->create(['user_id' => $user->id, 'status' => 'active', 'enrolled_at' => now()]);
    }

    private function quiz(Course $course): Quiz
    {
        $quiz = Quiz::create(['title' => 'Knowledge check', 'course_id' => $course->id, 'type' => 'lesson_check',
            'question_count' => 2, 'time_limit_minutes' => 10, 'passing_score_pct' => 70, 'is_published' => true]);
        $questions = Question::factory()->count(2)->create(['course_id' => $course->id]);
        $quiz->questions()->attach($questions->modelKeys());

        return $quiz;
    }

    public function test_course_review_metadata_is_persisted_and_review_screen_loads(): void
    {
        $facilitator = $this->actor('facilitator');
        $admin = $this->actor('platform_admin');
        $course = Course::factory()->create(['author_id' => $facilitator->id, 'created_by_role' => 'facilitator', 'approval_status' => 'draft', 'is_published' => false]);
        $this->lesson($course);
        Notification::fake();
        $this->actingAs($facilitator)->post("/facilitator/courses/{$course->id}/submit-review")->assertSessionHasNoErrors();
        $this->assertSame('pending_review', $course->fresh()->approval_status);
        $this->actingAs($admin)->get(route('admin.course-approvals.show', $course))->assertOk();
        $this->post(route('admin.course-approvals.approve', $course), ['publish_immediately' => true])->assertSessionHasNoErrors();
        $this->assertTrue($course->fresh()->is_published);
        $this->assertSame($admin->id, $course->fresh()->approvedBy->id);
    }

    public function test_empty_course_cannot_be_published(): void
    {
        $course = Course::factory()->create(['is_published' => false]);
        $this->actingAs($this->actor('platform_admin'))->put(route('admin.courses.publish', $course))->assertSessionHasErrors('readiness');
        $this->assertFalse($course->fresh()->is_published);
    }

    public function test_ready_facilitator_course_still_requires_approval(): void
    {
        $course = Course::factory()->create(['is_published' => false, 'created_by_role' => 'facilitator', 'approval_status' => 'draft']);
        $this->lesson($course);
        $this->actingAs($this->actor('platform_admin'))->put(route('admin.courses.publish', $course))->assertSessionHasErrors('readiness');
    }

    public function test_unenrolled_user_cannot_complete_download_or_commit_lesson(): void
    {
        $course = Course::factory()->create();
        $lesson = $this->lesson($course, ['pdf_url' => 'https://example.test/private.pdf', 'scorm_path' => 'package/index.html']);
        $this->actingAs(User::factory()->create());
        $this->post("/lessons/{$lesson->id}/complete")->assertForbidden();
        $this->get("/lessons/{$lesson->id}/download/pdf")->assertForbidden();
        $this->postJson("/api/scorm/commit/{$lesson->id}", ['cmi_core_lesson_status' => 'completed'])->assertForbidden();
        $this->assertDatabaseCount('lesson_progress', 0);
        $this->assertDatabaseCount('scorm_progress', 0);
    }

    public function test_hidden_and_drip_lessons_are_not_accessible_to_enrolled_users(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $this->enroll($user, $course);
        $lesson = $this->lesson($course, ['is_published' => false]);
        $this->actingAs($user)->post("/lessons/{$lesson->id}/complete")->assertNotFound();
        $lesson->update(['is_published' => true, 'unlock_type' => 'date', 'unlock_at' => now()->addDay()]);
        $this->post("/lessons/{$lesson->id}/complete")->assertForbidden();
    }

    public function test_suspended_enrollment_cannot_write_progress(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $this->enroll($user, $course);
        $lesson = $this->lesson($course);
        $course->enrollments()->update(['status' => 'suspended']);
        $this->actingAs($user)->post("/lessons/{$lesson->id}/complete")->assertForbidden();
        $this->assertSame('suspended', $course->enrollments()->first()->status);
    }

    public function test_unanswered_questions_count_against_the_score(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $this->enroll($user, $course);
        $quiz = $this->quiz($course);
        $this->actingAs($user)->followingRedirects()->post("/quizzes/{$quiz->id}/start")->assertOk();
        $attempt = QuizAttempt::firstOrFail();
        $this->post("/quiz-attempts/{$attempt->id}/answer", ['question_id' => $attempt->question_snapshot[0]['id'], 'selected_answer_ids' => ['A'], 'time_spent_seconds' => 10])->assertSessionHasNoErrors();
        $this->post("/quiz-attempts/{$attempt->id}/complete")->assertRedirect();
        $this->assertEquals(50, $attempt->fresh()->score_pct);
    }

    public function test_attempt_rejects_foreign_questions_and_uses_saved_answer_key(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $this->enroll($user, $course);
        $quiz = $this->quiz($course);
        $this->actingAs($user)->followingRedirects()->post("/quizzes/{$quiz->id}/start")->assertOk();
        $attempt = QuizAttempt::firstOrFail();
        $foreign = Question::factory()->create();
        $this->postJson("/quiz-attempts/{$attempt->id}/answer", ['question_id' => $foreign->id, 'selected_answer_ids' => ['A'], 'time_spent_seconds' => 10])->assertUnprocessable();
        $id = $attempt->question_snapshot[0]['id'];
        Question::find($id)->update(['correct_answer_ids' => ['B']]);
        $this->post("/quiz-attempts/{$attempt->id}/answer", ['question_id' => $id, 'selected_answer_ids' => ['A'], 'time_spent_seconds' => 10])->assertSessionHasNoErrors();
        $this->assertTrue($attempt->responses()->first()->is_correct);
    }

    public function test_expired_attempt_rejects_new_answers(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $this->enroll($user, $course);
        $quiz = $this->quiz($course);
        $this->actingAs($user)->followingRedirects()->post("/quizzes/{$quiz->id}/start")->assertOk();
        $attempt = QuizAttempt::firstOrFail();
        $attempt->update(['expires_at' => now()->subMinute()]);
        $this->postJson("/quiz-attempts/{$attempt->id}/answer", ['question_id' => $attempt->question_snapshot[0]['id'], 'selected_answer_ids' => ['A'], 'time_spent_seconds' => 10])->assertConflict();
    }

    public function test_exam_api_does_not_disclose_answers_and_resumes_the_same_questions(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $this->enroll($user, $course);
        Question::factory()->count(5)->create(['course_id' => $course->id]);
        $exam = MockExam::factory()->create(['course_id' => $course->id]);
        $first = $this->actingAs($user)->postJson("/api/exams/mock/{$exam->id}/generate")->assertOk();
        $this->assertArrayNotHasKey('correct_answer_ids', $first->json('data.questions.0'));
        $this->assertArrayNotHasKey('explanation_correct', $first->json('data.questions.0'));
        $again = $this->postJson("/api/exams/mock/{$exam->id}/generate")->assertOk();
        $this->assertSame($first->json('data.questions'), $again->json('data.questions'));
    }

    public function test_sponsor_cannot_change_another_organizers_meeting(): void
    {
        $owner = User::factory()->create();
        $meeting = Meeting::create(['title' => 'Review', 'organizer_id' => $owner->id, 'type' => 'review', 'scheduled_at' => now()->addDay(), 'status' => 'scheduled']);
        $this->actingAs($this->actor('sponsor'))->post("/sponsor/meetings/{$meeting->id}/notes", ['notes' => 'Changed'])->assertForbidden();
        $this->assertNull($meeting->fresh()->notes);
    }

    public function test_organization_admin_cannot_access_global_crm(): void
    {
        $this->actingAs($this->actor('org_admin'))->get('/admin/erp')->assertRedirect();
        $this->assertNotNull(session('error'));
    }

    public function test_legacy_payment_initialization_rejects_someone_elses_order(): void
    {
        $order = Order::create(['user_id' => User::factory()->create()->id, 'subtotal' => 1000, 'total' => 1000, 'currency' => 'NGN', 'status' => 'pending']);
        $this->actingAs(User::factory()->create())->postJson('/payments/paystack/initialize', ['order_id' => $order->id, 'amount' => 100])->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_unsigned_or_unconfigured_payment_callbacks_fail_closed(): void
    {
        foreach (['/webhooks/paystack', '/payments/webhook/flutterwave', '/payments/webhook/stripe'] as $path) {
            $this->postJson($path, ['type' => 'checkout.session.completed'])->assertStatus(400);
        }
        Http::assertNothingSent();
    }

    public function test_stripe_signature_rejects_stale_replays(): void
    {
        config(['services.stripe.webhook_secret' => 'local-test-secret']);
        $payload = json_encode(['type' => 'noop']);
        $timestamp = time() - 600;
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'local-test-secret');
        $this->call('POST', '/payments/webhook/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => "t=$timestamp,v1=$signature"], $payload)->assertStatus(400);
    }

    public function test_organization_overview_never_returns_other_organizations_people(): void
    {
        $organization = Organization::create(['name' => 'Acme Learning', 'contact_email' => 'admin@acme.test', 'max_seats' => 25, 'is_active' => true]);
        $other = Organization::create(['name' => 'Another Organization', 'contact_email' => 'admin@other.test', 'is_active' => true]);
        $admin = $this->actor('org_admin');
        $admin->update(['organization_id' => $organization->id]);
        $outside = User::factory()->create(['organization_id' => $other->id]);
        $this->actingAs($admin)->get('/organization')->assertInertia(fn ($page) => $page
            ->component('Organization/Dashboard')->where('stats.people', 1)->has('people.data', 1)
            ->where('people.data.0.id', $admin->id));
        $this->get('/organization?search='.urlencode($outside->email))->assertInertia(fn ($page) => $page->has('people.data', 0));
    }

    public function test_deactivated_accounts_cannot_login_or_continue_an_existing_session(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->actingAs($user)->get('/my-courses')->assertForbidden();
        $this->assertGuest();
    }

    public function test_paid_plan_cannot_be_activated_without_payment(): void
    {
        $plan = Plan::create(['name' => 'Professional', 'slug' => 'professional', 'price' => 10000, 'currency' => 'NGN', 'is_active' => true]);
        $this->actingAs(User::factory()->create())->postJson("/subscriptions/{$plan->id}/subscribe")->assertUnprocessable();
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_answers_cannot_be_reviewed_while_attempt_is_active(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $this->enroll($user, $course);
        $quiz = $this->quiz($course);
        $this->actingAs($user)->followingRedirects()->post("/quizzes/{$quiz->id}/start")->assertOk();
        $attempt = QuizAttempt::firstOrFail();
        $this->getJson("/quiz-attempts/{$attempt->id}/review")->assertConflict();
    }

    public function test_legacy_attempt_is_preserved_as_abandoned_and_can_restart(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $this->enroll($user, $course);
        $quiz = $this->quiz($course);
        $old = $quiz->attempts()->create(['user_id' => $user->id, 'started_at' => now(), 'status' => 'in_progress', 'total_count' => 2]);
        $this->actingAs($user)->followingRedirects()->post("/quizzes/{$quiz->id}/start")->assertOk();
        $this->assertSame('abandoned', $old->fresh()->status);
        $this->assertNotEmpty(QuizAttempt::latest('id')->first()->question_snapshot);
    }

    public function test_exam_pool_never_uses_unpublished_questions_to_fill_shortfall(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $this->enroll($user, $course);
        Question::factory()->count(5)->create(['course_id' => $course->id, 'status' => 'draft']);
        $exam = MockExam::factory()->create(['course_id' => $course->id]);
        $this->actingAs($user)->postJson("/api/exams/mock/{$exam->id}/generate")->assertUnprocessable();
        $this->assertDatabaseCount('quiz_attempts', 0);
    }

    public function test_facilitator_cannot_reach_another_cohorts_assignment(): void
    {
        $facilitator = $this->actor('facilitator');
        $course = Course::factory()->create();
        $mine = Cohort::create(['course_id' => $course->id, 'name' => 'Mine', 'facilitator_id' => $facilitator->id, 'start_date' => today(), 'end_date' => today()->addMonth(), 'status' => 'active']);
        $other = Cohort::create(['course_id' => $course->id, 'name' => 'Other', 'facilitator_id' => User::factory()->create()->id, 'start_date' => today(), 'end_date' => today()->addMonth(), 'status' => 'active']);
        $assignment = Assignment::create(['course_id' => $course->id, 'cohort_id' => $other->id, 'title' => 'Confidential assessment', 'description' => 'Instructions', 'created_by' => $other->facilitator_id]);
        $this->actingAs($facilitator)->get("/facilitator/cohorts/{$mine->id}/assignments/{$assignment->id}/edit")->assertNotFound();
        $this->delete("/facilitator/cohorts/{$mine->id}/assignments/{$assignment->id}")->assertNotFound();
        $this->assertModelExists($assignment);
    }

    public function test_stripe_valid_callback_checks_amount_and_is_idempotent(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $product = Product::create(['name' => 'Course access', 'type' => 'course', 'slug' => 'course-access', 'price' => 100, 'currency' => 'NGN', 'is_active' => true, 'productable_type' => Course::class, 'productable_id' => $course->id]);
        $order = Order::create(['user_id' => $user->id, 'subtotal' => 100, 'total' => 100, 'currency' => 'NGN', 'status' => 'pending']);
        $order->items()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100, 'total_price' => 100]);
        $payment = Payment::create(['user_id' => $user->id, 'order_id' => $order->id, 'gateway' => 'stripe', 'gateway_reference' => 'session_123', 'amount' => 100, 'currency' => 'NGN', 'status' => 'pending']);
        config(['services.stripe.webhook_secret' => 'local-test-secret']);
        $send = function (int $amount) {
            $body = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'session_123', 'payment_status' => 'paid', 'currency' => 'ngn', 'amount_total' => $amount]]]);
            $timestamp = time();
            $signature = hash_hmac('sha256', "$timestamp.$body", 'local-test-secret');

            return $this->call('POST', '/payments/webhook/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => "t=$timestamp,v1=$signature"], $body);
        };
        $send(10)->assertOk();
        $this->assertDatabaseCount('course_enrollments', 0);
        $send(10000)->assertOk();
        $send(10000)->assertOk();
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertDatabaseCount('course_enrollments', 1);
    }
}
