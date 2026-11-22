<?php

namespace App\Services;

use App\Exceptions\ExternalIntegrationException;
use App\Models\Course;
use App\Models\Question;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AiContentService
{
    /**
     * Parse text content (transcript, lesson text) and generate a quiz via LLM.
     */
    public function generateQuizFromText($content, $courseId, $domain, $difficulty = 'medium', $count = 5)
    {
        app(CurriculumRevisionService::class)->assertEditable(Course::findOrFail($courseId));
        // Generate draft questions for human review.
        $prompt = "Generate {$count} {$difficulty} multiple-choice questions from the following text. 
                   Return ONLY a strict JSON array where each object has:
                   - 'stem' (the question)
                   - 'options' (Array of exactly 4 strings)
                   - 'correct_index' (Integer 0-3 representing the correct option in the array)
                   - 'explanation' (Why it's correct)
                   
                   TEXT: {$content}";

        $raw = app(AIService::class)->chat($prompt, 'You are an instructional design AI. Return only the requested JSON array.', 0.6, 4096);
        if (preg_match('/```(?:json)?\s*([\s\S]+?)```/', $raw, $match)) {
            $raw = $match[1];
        }
        $resultJson = json_decode(trim($raw), true);

        $questionsPayload = is_array($resultJson) && isset($resultJson['questions']) ? $resultJson['questions'] : $resultJson;

        $validator = Validator::make(['questions' => $questionsPayload], [
            'questions' => 'required|array|list|size:'.$count,
            'questions.*.stem' => 'required|string|max:30000',
            'questions.*.options' => 'required|array|list|size:4',
            'questions.*.options.*' => 'required|string|max:10000',
            'questions.*.correct_index' => 'required|integer|min:0|max:3',
            'questions.*.explanation' => 'required|string|max:30000',
        ]);
        if ($validator->fails()) {
            throw new ExternalIntegrationException('The AI returned an incomplete quiz. No questions were saved. Please retry.', 502);
        }

        return DB::transaction(function () use ($questionsPayload, $courseId, $domain, $difficulty) {
            $course = Course::whereKey($courseId)->lockForUpdate()->firstOrFail();
            app(CurriculumRevisionService::class)->assertEditable($course);
            $createdQuestions = collect();

            foreach ($questionsPayload as $qInfo) {
                $options = [];
                $correctId = null;

                foreach ($qInfo['options'] as $index => $optText) {
                    // Ensure 1-based ID for consistency with our schema assumptions
                    $id = (string) ($index + 1);
                    $options[] = ['id' => $id, 'text' => $optText];

                    if ($index === (int) $qInfo['correct_index']) {
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
                    'options' => $options,
                    'correct_answer_ids' => [$correctId],
                    'explanation_correct' => $qInfo['explanation'],
                    'status' => 'draft', // Saved in draft for Faciliator review
                    'version' => 1,
                ]);

                $createdQuestions->push($question);
            }

            return $createdQuestions;
        });
    }
}
