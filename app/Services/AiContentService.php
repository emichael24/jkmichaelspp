<?php

namespace App\Services;

use App\Models\Question;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Exception;

class AiContentService
{
    protected $openaiKey;

    public function __construct()
    {
        // Using OpenAI or Gemini contextually
        $this->openaiKey = config('services.openai.key'); 
    }

    /**
     * Parse text content (transcript, lesson text) and generate a quiz via LLM.
     */
    public function generateQuizFromText($content, $courseId, $domain, $difficulty = 'medium', $count = 5)
    {
        // Mock prompt to an LLM
        $prompt = "Generate {$count} {$difficulty} multiple-choice questions from the following text. 
                   Return ONLY a strict JSON array where each object has:
                   - 'stem' (the question)
                   - 'options' (Array of exactly 4 strings)
                   - 'correct_index' (Integer 0-3 representing the correct option in the array)
                   - 'explanation' (Why it's correct)
                   
                   TEXT: {$content}";

        $response = Http::withToken($this->openaiKey)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an instructional design AI.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.7
            ]);

        if (!$response->successful()) {
            throw new Exception("AI Generation failed: " . $response->body());
        }

        $resultJson = json_decode($response->json('choices.0.message.content'), true);
        
        $questionsPayload = is_array($resultJson) && isset($resultJson['questions']) ? $resultJson['questions'] : $resultJson;

        $createdQuestions = collect();

        foreach ($questionsPayload as $qInfo) {
            $options = [];
            $correctId = null;

            foreach ($qInfo['options'] as $index => $optText) {
                // Ensure 1-based ID for consistency with our schema assumptions
                $id = $index + 1; 
                $options[] = ['id' => $id, 'text' => $optText];
                
                if ($index === $qInfo['correct_index']) {
                    $correctId = $id;
                }
            }

            $question = Question::create([
                'uuid' => (string) Str::uuid(),
                'course_id' => $courseId,
                'domain' => $domain,
                'type' => 'multiple_choice',
                'difficulty' => $difficulty,
                'stem' => $qInfo['stem'],
                'options' => json_encode($options),
                'correct_answer_ids' => json_encode([$correctId]),
                'explanation_correct' => $qInfo['explanation'],
                'status' => 'draft', // Saved in draft for Faciliator review
                'version' => 1
            ]);

            $createdQuestions->push($question);
        }

        return $createdQuestions;
    }
}
