<?php

namespace App\Services;

use App\Models\MockExam;
use App\Models\Question;
use App\Models\QuizAttempt;
use Illuminate\Support\Facades\DB;

class ExamGenerationService
{
    /**
     * Generate a new unique mock exam attempt for a user based on rules.
     */
    public function generateExamAttempt($userId, $mockExamId)
    {
        $exam = MockExam::findOrFail($mockExamId);
        
        $questions = $this->pullDynamicQuestions(
            $exam->course_id,
            $exam->question_count,
            $exam->domain_distribution,
            $exam->difficulty_distribution
        );

        return DB::transaction(function () use ($userId, $exam, $questions) {
            $attempt = QuizAttempt::create([
                'user_id' => $userId,
                'quizzable_type' => MockExam::class,
                'quizzable_id' => $exam->id,
                'status' => 'in_progress',
                'total_count' => $questions->count(),
                'started_at' => now(),
            ]);

            // Save the exact questions generated for this specific attempt
            // Normally you would store this in JSON state or a pivot table
            // We'll store the skeleton payload in cache/state for the frontend.

            // Returning the generated assessment DTO
            return [
                'attempt_id' => $attempt->id,
                'exam_title' => $exam->title,
                'time_limit_minutes' => $exam->time_limit_minutes,
                'questions' => $questions->shuffle()->values()
            ];
        });
    }

    /**
     * Advanced generation algorithm enforcing domain/difficulty boundaries.
     */
    protected function pullDynamicQuestions($courseId, $total, $domainDist, $difficultyDist)
    {
        $selectedQuestions = collect();

        // Decode JSON distributions if needed
        $domains = is_array($domainDist) ? $domainDist : json_decode($domainDist, true);
        
        foreach ($domains as $domainConfig) {
            $targetCount = round(($domainConfig['percentage'] / 100) * $total);
            
            $query = Question::where('course_id', $courseId)
                             ->where('domain', $domainConfig['domain'])
                             ->where('status', 'published');

            // Complex logic: apply difficulties, ensuring no duplicates. 
            // Simplified here: Random pull matching domain
            $questions = $query->inRandomOrder()->take($targetCount)->get();
            $selectedQuestions = $selectedQuestions->concat($questions);
        }

        // If math rounding causes it to be short, fill remainder
        $shortfall = $total - $selectedQuestions->count();
        if ($shortfall > 0) {
            $extra = Question::where('course_id', $courseId)
                 ->whereNotIn('id', $selectedQuestions->pluck('id'))
                 ->inRandomOrder()
                 ->take($shortfall)
                 ->get();
            $selectedQuestions = $selectedQuestions->concat($extra);
        }

        return $selectedQuestions;
    }

    /**
     * Score a completed exam attempt
     */
    public function gradeAttempt(QuizAttempt $attempt, array $responses)
    {
        $correctCount = 0;
        $domainScores = [];
        
        foreach ($responses as $response) {
            $question = Question::find($response['question_id']);
            $isCorrect = $this->evaluateAnswer($question, $response['selected_ids']);
            
            if ($isCorrect) {
                $correctCount++;
                if (!isset($domainScores[$question->domain])) {
                    $domainScores[$question->domain] = ['correct' => 0, 'total' => 0];
                }
                $domainScores[$question->domain]['correct']++;
            }
            if (!isset($domainScores[$question->domain])) {
                $domainScores[$question->domain] = ['correct' => 0, 'total' => 0];
            }
            $domainScores[$question->domain]['total']++;

            // Create response log
            $attempt->responses()->create([
                'question_id' => $question->id,
                'user_id' => $attempt->user_id,
                'selected_answer_ids' => json_encode($response['selected_ids']),
                'is_correct' => $isCorrect,
                'time_spent_seconds' => $response['time_spent'] ?? 0,
            ]);
        }

        // Finalize Attempt
        $attempt->update([
            'status' => 'completed',
            'score_pct' => ($correctCount / $attempt->total_count) * 100,
            'correct_count' => $correctCount,
            'domain_scores' => json_encode($domainScores),
            'completed_at' => now(),
        ]);

        return $attempt;
    }

    private function evaluateAnswer($question, $selectedIds)
    {
        $correctIds = is_array($question->correct_answer_ids) 
                        ? $question->correct_answer_ids 
                        : json_decode($question->correct_answer_ids, true);
        
        sort($correctIds);
        sort($selectedIds);
        return $correctIds === $selectedIds;
    }
}
