<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AiStudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_facilitator_can_generate_quiz_via_ai()
    {
        // 1. Setup Data & Roles
        $user = User::factory()->create();
        Role::findOrCreate('facilitator', 'web');
        $user->assignRole('facilitator'); // Assuming Spatie roles or similar

        $course = Course::factory()->create(['author_id' => $user->id, 'is_published' => false]);

        // Exercise the configured integration contract without contacting a provider.
        config(['services.flexichurch.base_url' => 'https://ai-provider.test', 'services.flexichurch.api_key' => 'test-only-key']);
        Http::preventStrayRequests();
        Http::fake([
            'ai-provider.test/ai/chat' => Http::response([
                'status' => 'success',
                'data' => [
                    'content' => json_encode([
                        'questions' => [
                            [
                                'stem' => 'What is Agile?',
                                'options' => ['A', 'B', 'C', 'D'],
                                'correct_index' => 0,
                                'explanation' => 'Because A is right.',
                            ],
                        ],
                    ]),
                ],
            ], 200),
        ]);

        // 3. Act
        $response = $this->actingAs($user)
            ->postJson('/facilitator/ai-studio/generate-quiz', [
                'course_id' => $course->id,
                'content_text' => 'We will learn about Agile methodologies today.',
                'domain' => 'Agile Framework',
                'difficulty' => 'easy',
                'count' => 1,
            ]);

        // 4. Assert
        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Successfully generated 1 questions.',
            ]);

        $this->assertDatabaseHas('questions', [
            'course_id' => $course->id,
            'domain' => 'Agile Framework',
            'difficulty' => 'easy',
            'status' => 'draft',
        ]);

        // Assert the mock hit the endpoint
        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://ai-provider.test/ai/chat'
                && $request->method() === 'POST'
                && str_contains($request['message'], 'Agile methodologies');
        });
    }
}
