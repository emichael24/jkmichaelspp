<?php

namespace App\Services;

use App\Models\MockExam;
use App\Models\QuizAttempt;
use App\Models\User;

class ExamGenerationService
{
    /**
     * Generate a new unique mock exam attempt for a user based on rules.
     */
    public function generateExamAttempt($userId, $mockExamId)
    {
        $exam = MockExam::findOrFail($mockExamId);
        abort_unless($exam->is_published, 404);
        app(LearningAccessService::class)->assertCourse($exam->course, User::findOrFail($userId));

        $attempt = app(AssessmentAttemptService::class)->start($exam, User::findOrFail($userId), fn () => $this->pullDynamicQuestions($exam->course_id, $exam->question_count, $exam->domain_distribution, $exam->difficulty_distribution));

        return ['attempt_id' => $attempt->id, 'exam_title' => $exam->title, 'started_at' => $attempt->started_at, 'expires_at' => $attempt->expires_at, 'status' => $attempt->status, 'review_url' => route('mock-exams.report', $attempt),
            'time_limit_minutes' => $exam->time_limit_minutes, 'questions' => app(AssessmentAttemptService::class)->learnerQuestions($attempt), 'current_answers' => $attempt->draft_responses ?? []];

    }

    /**
     * Advanced generation algorithm enforcing domain/difficulty boundaries.
     */
    public function pullDynamicQuestions($courseId, $total, $domainDist, $difficultyDist)
    {
        return app(AssessmentBlueprintService::class)->select((int) $courseId, (int) $total, $domainDist, $difficultyDist);
    }

    /**
     * Score a completed exam attempt
     */
    public function gradeAttempt(QuizAttempt $attempt, array $responses)
    {
        return app(AssessmentAttemptService::class)->complete($attempt, $responses);
    }
}
