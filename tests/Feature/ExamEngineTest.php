<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\MockExam;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_generate_a_mock_exam_attempt()
    {
        // 1. Setup Data
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $course->enrollments()->create(['user_id' => $user->id, 'status' => 'active', 'enrolled_at' => now()]);

        // Create some published questions
        Question::factory()->count(10)->create([
            'course_id' => $course->id,
            'domain' => 'Project Management',
            'status' => 'published',
            'correct_answer_ids' => ['A'],
        ]);

        $mockExam = MockExam::factory()->create([
            'course_id' => $course->id,
            'question_count' => 5,
            'time_limit_minutes' => 60,
            'domain_distribution' => ['Project Management' => 100],
        ]);

        // 2. Act
        $response = $this->actingAs($user)
            ->postJson("/api/exams/mock/{$mockExam->id}/generate");

        // 3. Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'attempt_id',
                    'exam_title',
                    'time_limit_minutes',
                    'questions',
                ],
            ]);

        // Ensure 5 questions were actually pulled
        $this->assertCount(5, $response->json('data.questions'));
    }
}
