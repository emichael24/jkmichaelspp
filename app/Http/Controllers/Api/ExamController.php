<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MockExam;
use App\Models\QuizAttempt;
use App\Services\AssessmentAttemptService;
use App\Services\ExamGenerationService;
use App\Services\LearningAccessService;
use Illuminate\Http\Request;
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
        $exam = MockExam::findOrFail($mockExamId);
        abort_unless($exam->is_published, 404);
        app(LearningAccessService::class)->assertCourse($exam->course, $user);

        // Generate a new one
        $examPayload = $this->examService->generateExamAttempt($user->id, $mockExamId);

        return response()->json([
            'status' => 'success',
            'data' => $examPayload,
        ]);
    }

    /**
     * Periodically save state to prevent data loss
     */
    public function autoSave(Request $request, $attemptId)
    {
        $attempt = QuizAttempt::findOrFail($attemptId);

        if ($attempt->user_id !== Auth::id()) {
            abort(403);
        }

        abort_unless($attempt->quizzable_type === MockExam::class, 404);
        app(LearningAccessService::class)->assertCourse($attempt->quizzable->course, $request->user());
        $request->validate(['current_answers' => 'present|array|max:300', 'current_answers.*' => 'array|max:100', 'current_answers.*.*' => 'string|max:1000']);
        app(AssessmentAttemptService::class)->saveDraft($attempt, $request->current_answers);

        return response()->json(['status' => 'saved']);
    }

    public function pause(Request $request, $attemptId)
    {
        $this->autoSave($request, $attemptId);
        app(AssessmentAttemptService::class)->pause(QuizAttempt::findOrFail($attemptId));

        return response()->json(['status' => 'paused']);
    }

    /**
     * Submit and grade
     */
    public function submit(Request $request, $attemptId)
    {
        $request->validate([
            'responses' => 'present|array|max:300',
            'responses.*.question_id' => 'required|integer|distinct',
            'responses.*.selected_ids' => 'required|array|max:100',
            'responses.*.selected_ids.*' => 'string|max:1000',
            'responses.*.time_spent' => 'nullable|integer|min:0',
        ]);

        $attempt = QuizAttempt::findOrFail($attemptId);
        if ($attempt->user_id !== Auth::id()) {
            abort(403);
        }
        abort_unless($attempt->quizzable_type === MockExam::class, 404);
        app(LearningAccessService::class)->assertCourse($attempt->quizzable->course, $request->user());

        $gradedAttempt = $this->examService->gradeAttempt($attempt, $request->responses);

        return response()->json([
            'status' => 'success',
            'score' => $gradedAttempt->score_pct,
            'passed' => $gradedAttempt->score_pct >= $attempt->quizzable->passing_score_pct,
            'domain_breakdown' => $gradedAttempt->domain_scores,
        ]);
    }
}
