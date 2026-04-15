<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\MockExam;
use App\Models\Product;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CriticalPathTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::define('own', function ($user, $attempt) {
            return $attempt && $attempt->user_id === $user->id;
        });
    }

    public function test_user_can_login_and_access_dashboard(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password'),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->actingAs($user)->get('/dashboard')->assertStatus(200);
    }

    public function test_user_can_enroll_in_free_course(): void
    {
        $user = User::factory()->create();
        $course = $this->makeCourse();

        $this->actingAs($user)
            ->post("/courses/{$course->id}/enroll")
            ->assertRedirect();

        $this->assertDatabaseHas('course_enrollments', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
    }

    public function test_user_can_complete_quiz_attempt(): void
    {
        $user = User::factory()->create();
        $course = $this->makeCourse();
        $question = $this->makeQuestion($course);
        $quiz = $this->makeQuiz($course, $question);

        $this->actingAs($user)
            ->post("/quizzes/{$quiz->id}/start")
            ->assertStatus(200);

        $attempt = QuizAttempt::first();
        $this->assertNotNull($attempt);

        $this->actingAs($user)
            ->post("/quiz-attempts/{$attempt->id}/answer", [
                'question_id' => $question->id,
                'selected_answer_ids' => $question->correct_answer_ids,
                'confidence_level' => 'high',
                'time_spent_seconds' => 12,
                'is_flagged' => false,
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post("/quiz-attempts/{$attempt->id}/complete")
            ->assertRedirect();

        $attempt->refresh();
        $this->assertEquals('completed', $attempt->status);
        $this->assertEquals(100.00, (float) $attempt->score_pct);
    }

    public function test_user_can_complete_mock_exam_attempt(): void
    {
        $user = User::factory()->create();
        $course = $this->makeCourse();
        $question = $this->makeQuestion($course, 'General');
        $mock = $this->makeMockExam($course, ['General' => 1]);

        $this->actingAs($user)
            ->post("/mock-exams/{$mock->id}/start")
            ->assertStatus(200);

        $attempt = QuizAttempt::first();
        $this->assertNotNull($attempt);

        $this->actingAs($user)
            ->post("/mock-attempts/{$attempt->id}/answer", [
                'question_id' => $question->id,
                'selected_answer_ids' => $question->correct_answer_ids,
                'confidence_level' => 'medium',
                'time_spent_seconds' => 30,
                'is_flagged' => false,
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post("/mock-attempts/{$attempt->id}/complete")
            ->assertRedirect();

        $attempt->refresh();
        $this->assertEquals('completed', $attempt->status);
        $this->assertEquals(100.00, (float) $attempt->score_pct);
    }

    public function test_user_can_create_order_and_initiate_payment(): void
    {
        $user = User::factory()->create();
        $course = $this->makeCourse();
        $product = Product::create([
            'productable_type' => Course::class,
            'productable_id' => $course->id,
            'name' => $course->title,
            'slug' => 'course-' . $course->id,
            'price' => 1500,
            'currency' => 'NGN',
            'is_active' => true,
        ]);

        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://paystack.test/checkout',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->post('/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
            'payment_gateway' => 'paystack',
        ]);

        $response->assertStatus(409);
        $response->assertHeader('X-Inertia-Location', 'https://paystack.test/checkout');

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_user_can_view_and_download_certificate(): void
    {
        $user = User::factory()->create();
        $course = $this->makeCourse();

        $certificate = Certificate::create([
            'user_id' => $user->id,
            'certifiable_type' => Course::class,
            'certifiable_id' => $course->id,
            'title' => 'Course Completion',
            'issued_at' => now(),
            'is_revoked' => false,
        ]);

        $this->actingAs($user)
            ->get("/certificates/{$certificate->uuid}")
            ->assertStatus(200);

        $this->actingAs($user)
            ->get("/certificates/{$certificate->uuid}/download")
            ->assertStatus(200);
    }

    public function test_admin_routes_require_role(): void
    {
        $admin = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'platform_admin']);
        $admin->assignRole($role);

        $this->actingAs($admin)->get('/admin')->assertStatus(200);

        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin')->assertStatus(403);
    }

    private function makeCourse(): Course
    {
        $author = User::factory()->create();
        $category = Category::create([
            'name' => 'General',
            'slug' => 'general',
            'is_active' => true,
        ]);

        return Course::create([
            'category_id' => $category->id,
            'title' => 'Test Course ' . now()->timestamp,
            'slug' => 'test-course-' . now()->timestamp,
            'level' => 'beginner',
            'type' => 'self_paced',
            'price' => 0,
            'currency' => 'NGN',
            'is_published' => true,
            'published_at' => now(),
            'author_id' => $author->id,
            'total_enrollments' => 0,
            'avg_rating' => 0,
        ]);
    }

    private function makeQuestion(Course $course, string $domain = 'General'): Question
    {
        $options = [
            ['id' => 'A', 'text' => 'Option A'],
            ['id' => 'B', 'text' => 'Option B'],
        ];

        return Question::create([
            'course_id' => $course->id,
            'domain' => $domain,
            'type' => 'multiple_choice',
            'difficulty' => 'easy',
            'stem' => 'Test question?',
            'options' => $options,
            'correct_answer_ids' => ['A'],
            'explanation_correct' => 'Because A is correct.',
            'time_estimate_seconds' => 30,
            'status' => 'published',
        ]);
    }

    private function makeQuiz(Course $course, Question $question): Quiz
    {
        $quiz = Quiz::create([
            'title' => 'Test Quiz',
            'course_id' => $course->id,
            'type' => 'lesson_check',
            'question_count' => 1,
            'time_limit_minutes' => 10,
            'passing_score_pct' => 70,
            'is_randomized' => false,
            'is_published' => true,
        ]);

        $quiz->questions()->attach($question->id, ['sort_order' => 0]);

        return $quiz;
    }

    private function makeMockExam(Course $course, array $distribution): MockExam
    {
        return MockExam::create([
            'course_id' => $course->id,
            'title' => 'Test Mock Exam',
            'description' => 'Mock exam description',
            'type' => 'mini_mock',
            'question_count' => array_sum($distribution),
            'time_limit_minutes' => 60,
            'passing_score_pct' => 65,
            'domain_distribution' => $distribution,
            'difficulty_distribution' => null,
            'is_published' => true,
            'is_exam_simulation' => false,
            'allow_pause' => true,
        ]);
    }
}
