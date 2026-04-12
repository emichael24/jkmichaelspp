<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Course;
use Illuminate\Support\Facades\Http;

class AiStudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_facilitator_can_generate_quiz_via_ai()
    {
        // 1. Setup Data & Roles
        $user = User::factory()->create();
        $user->assignRole('facilitator'); // Assuming Spatie roles or similar
        
        $course = Course::factory()->create();

        // 2. Mock the OpenAI HTTP Response
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'questions' => [
                                    [
                                        'stem' => 'What is Agile?',
                                        'options' => ['A', 'B', 'C', 'D'],
                                        'correct_index' => 0,
                                        'explanation' => 'Because A is right.'
                                    ]
                                ]
                            ])
                        ]
                    ]
                ]
            ], 200)
        ]);

        // 3. Act
        $response = $this->actingAs($user)
            ->postJson("/facilitator/ai-studio/generate-quiz", [
                'course_id' => $course->id,
                'content_text' => 'We will learn about Agile methodologies today.',
                'domain' => 'Agile Framework',
                'difficulty' => 'easy',
                'count' => 1
            ]);

        // 4. Assert
        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'success',
                     'message' => 'Successfully generated 1 questions.'
                 ]);

        $this->assertDatabaseHas('questions', [
            'course_id' => $course->id,
            'domain' => 'Agile Framework',
            'difficulty' => 'easy',
            'status' => 'draft'
        ]);
        
        // Assert the mock hit the endpoint
        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            return str_contains($request->url(), 'api.openai.com');
        });
    }
}

