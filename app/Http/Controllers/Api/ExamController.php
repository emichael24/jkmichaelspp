<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MockExam;
use App\Models\QuizAttempt;
use App\Services\ExamGenerationService;
use Illuminate\Support\Facades\Auth;

class ExamController extends Controller
{
    protected $examService;

    public function __construct(ExamGenerationService $examService)
    {
        $this->examService = $examService;
    }

    /**
     * Start or resume an exam
     */
    public function generate(Request $request, $mockExamId)
    {
        $user = Auth::user();

        // Check if an in-progress attempt already exists to allow resuming
        $existingAttempt = QuizAttempt::where('user_id', $user->id)
            ->where('quizzable_type', MockExam::class)
            ->where('quizzable_id', $mockExamId)
            ->where('status', 'in_progress')
            ->first();

        if ($existingAttempt) {
            return response()->json([
                'status' => 'success',
                'message' => 'Resuming existing exam.',
                'data' => [
                    'attempt_id' => $existingAttempt->id,
                    'started_at' => $existingAttempt->started_at,
                    // Additional logic would re-hydrate the exactly generated questions here
                ]
            ]);
        }

        // Generate a new one
        $examPayload = $this->examService->generateExamAttempt($user->id, $mockExamId);

        return response()->json([
            'status' => 'success',
            'data' => $examPayload
        ]);
    }

    /**
     * Periodically save state to prevent data loss
     */
    public function autoSave(Request $request, $attemptId)
    {
        $attempt = QuizAttempt::findOrFail($attemptId);
        
        if ($attempt->user_id !== Auth::id()) abort(403);

        // In a real system, we'd save a draft JSON column of the current selected answers
        // $attempt->update(['draft_responses' => $request->current_answers]);

        return response()->json(['status' => 'saved']);
    }

    /**
     * Submit and grade
     */
    public function submit(Request $request, $attemptId)
    {
        $request->validate([
            'responses' => 'required|array',
        ]);

        $attempt = QuizAttempt::findOrFail($attemptId);
        if ($attempt->user_id !== Auth::id()) abort(403);
        if ($attempt->status !== 'in_progress') abort(400, 'Exam already submitted.');

        $gradedAttempt = $this->examService->gradeAttempt($attempt, $request->responses);

        return response()->json([
            'status' => 'success',
            'score' => $gradedAttempt->score_pct,
            'passed' => $gradedAttempt->score_pct >= $attempt->quizzable->passing_score_pct,
            'domain_breakdown' => json_decode($gradedAttempt->domain_scores, true)
        ]);
    }
}

