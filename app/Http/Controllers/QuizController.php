<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\QuestionResponse;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\SpacedRepetitionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class QuizController extends Controller
{
    /**
     * Show quiz details/start page.
     */
    public function show(Quiz $quiz)
    {
        $quiz->load(['course:id,title,slug', 'module:id,title', 'lesson:id,title']);
        $user = Auth::user();

        $previousAttempts = $quiz->attempts()
            ->forUser($user->id)
            ->completed()
            ->latest('completed_at')
            ->limit(5)
            ->get(['id', 'score_pct', 'correct_count', 'total_count', 'time_spent_seconds', 'completed_at']);

        $canAttempt = $quiz->canAttempt($user);
        $attemptCount = $quiz->attemptsBy($user);

        return Inertia::render('Quizzes/Show', [
            'quiz'             => $quiz,
            'previousAttempts' => $previousAttempts,
            'canAttempt'       => $canAttempt,
            'attemptCount'     => $attemptCount,
        ]);
    }

    /**
     * Start a new quiz attempt — loads the quiz player.
     */
    public function start(Quiz $quiz)
    {
        $user = Auth::user();

        if (! $quiz->canAttempt($user)) {
            return back()->with('error', 'Maximum attempts reached for this quiz.');
        }

        // Check for existing in-progress attempt
        $existingAttempt = $quiz->attempts()
            ->forUser($user->id)
            ->inProgress()
            ->first();

        if ($existingAttempt) {
            return $this->loadPlayer($quiz, $existingAttempt);
        }

        // Get questions for this attempt
        $questions = $quiz->getQuestionsForAttempt();

        // Create the attempt
        $attempt = QuizAttempt::create([
            'user_id'        => $user->id,
            'quizzable_type' => Quiz::class,
            'quizzable_id'   => $quiz->id,
            'status'         => 'in_progress',
            'total_count'    => $questions->count(),
            'started_at'     => now(),
        ]);

        // Increment exposure count for each question
        $questions->each(fn (Question $q) => $q->incrementExposure());

        return $this->loadPlayer($quiz, $attempt, $questions);
    }

    /**
     * Submit answer for a single question.
     */
    public function answer(Request $request, QuizAttempt $attempt)
    {
        $this->authorize('own', $attempt);

        if ($attempt->status !== 'in_progress') {
            return back()->with('error', 'This quiz attempt is no longer in progress.');
        }

        $validated = $request->validate([
            'question_id'        => 'required|exists:questions,id',
            'selected_answer_ids' => 'required|array',
            'selected_answer_ids.*' => 'string',
            'confidence_level'   => 'nullable|in:low,medium,high',
            'time_spent_seconds' => 'required|integer|min:0',
            'is_flagged'         => 'boolean',
        ]);

        $question = Question::findOrFail($validated['question_id']);
        $isCorrect = $question->isCorrect($validated['selected_answer_ids']);

        // Update or create the response
        QuestionResponse::updateOrCreate(
            [
                'quiz_attempt_id' => $attempt->id,
                'question_id'     => $validated['question_id'],
            ],
            [
                'user_id'            => Auth::id(),
                'selected_answer_ids' => $validated['selected_answer_ids'],
                'is_correct'         => $isCorrect,
                'time_spent_seconds' => $validated['time_spent_seconds'],
                'confidence_level'   => $validated['confidence_level'] ?? null,
                'is_flagged'         => $validated['is_flagged'] ?? false,
            ]
        );

        // Update question statistics
        $question->updateStats($isCorrect, $validated['time_spent_seconds']);

        return back()->with('answerSaved', true);
    }

    /**
     * Flag/unflag a question during the quiz.
     */
    public function flag(Request $request, QuizAttempt $attempt)
    {
        $this->authorize('own', $attempt);

        $request->validate([
            'question_id' => 'required|exists:questions,id',
            'is_flagged'  => 'required|boolean',
        ]);

        QuestionResponse::where('quiz_attempt_id', $attempt->id)
            ->where('question_id', $request->question_id)
            ->update(['is_flagged' => $request->is_flagged]);

        return back();
    }

    /**
     * Complete the quiz attempt — calculate scores and redirect to review.
     */
    public function complete(QuizAttempt $attempt)
    {
        $this->authorize('own', $attempt);

        if ($attempt->status !== 'in_progress') {
            return redirect()->route('quiz.review', $attempt->id);
        }

        $attempt->calculateScore();

        // Auto-add wrong answers to spaced repetition
        app(SpacedRepetitionService::class)->processCompletedAttempt($attempt);

        return redirect()->route('quiz.review', $attempt->id)
            ->with('success', 'Quiz completed! Review your results below.');
    }

    /**
     * Show quiz review page with explanations.
     */
    public function review(QuizAttempt $attempt)
    {
        $this->authorize('own', $attempt);

        $attempt->load('quizzable');

        $review = $attempt->getReview();

        $quizTitle = $attempt->quizzable->title ?? 'Quiz';

        return Inertia::render('Quizzes/Review', [
            'attempt' => [
                'id'                 => $attempt->id,
                'status'             => $attempt->status,
                'score_pct'          => $attempt->score_pct,
                'correct_count'      => $attempt->correct_count,
                'total_count'        => $attempt->total_count,
                'time_spent_seconds' => $attempt->time_spent_seconds,
                'domain_scores'      => $attempt->domain_scores,
                'started_at'         => $attempt->started_at,
                'completed_at'       => $attempt->completed_at,
                'passed'             => $attempt->isPassed(),
                'passing_score'      => $attempt->quizzable->passing_score_pct ?? 70,
            ],
            'quizTitle' => $quizTitle,
            'courseSlug' => $attempt->quizzable->course?->slug,
            'review'    => $review,
        ]);
    }

    /**
     * Load the quiz player Inertia page.
     */
    private function loadPlayer(Quiz $quiz, QuizAttempt $attempt, $questions = null)
    {
        if (! $questions) {
            // Reload questions from the attempt responses + remaining
            $answeredIds = $attempt->responses()->pluck('question_id');
            $allQuestions = $quiz->questions()->published()->get();

            if ($quiz->is_randomized) {
                $allQuestions = $allQuestions->shuffle();
            }

            $questions = $allQuestions->take($quiz->question_count);
        }

        // Get existing responses for this attempt
        $existingResponses = $attempt->responses()
            ->get()
            ->keyBy('question_id')
            ->map(fn ($r) => [
                'selected_answer_ids' => $r->selected_answer_ids,
                'confidence_level'    => $r->confidence_level,
                'time_spent_seconds'  => $r->time_spent_seconds,
                'is_flagged'          => $r->is_flagged,
            ]);

        // Sanitize questions — remove correct answers and explanations
        $sanitizedQuestions = $questions->map(fn (Question $q) => [
            'id'                    => $q->id,
            'uuid'                  => $q->uuid,
            'type'                  => $q->type,
            'stem'                  => $q->stem,
            'stem_media'            => $q->stem_media,
            'options'               => collect($q->options)->map(fn ($opt) => [
                'id'   => $opt['id'],
                'text' => $opt['text'],
                'media_url' => $opt['media_url'] ?? null,
            ])->toArray(),
            'domain'                => $q->domain,
            'time_estimate_seconds' => $q->time_estimate_seconds,
        ]);

        return Inertia::render('Quizzes/Player', [
            'quiz' => [
                'id'                 => $quiz->id,
                'title'              => $quiz->title,
                'type'               => $quiz->type,
                'question_count'     => $quiz->question_count,
                'time_limit_minutes' => $quiz->time_limit_minutes,
                'passing_score_pct'  => $quiz->passing_score_pct,
                'course_title'       => $quiz->course?->title,
                'course_slug'        => $quiz->course?->slug,
            ],
            'attempt'           => [
                'id'         => $attempt->id,
                'started_at' => $attempt->started_at,
                'status'     => $attempt->status,
            ],
            'questions'         => $sanitizedQuestions->values(),
            'existingResponses' => $existingResponses,
        ]);
    }

    /**
     * Gate: user can only interact with their own attempt.
     */
    private function authorize(string $ability, QuizAttempt $attempt): void
    {
        if ($attempt->user_id !== Auth::id()) {
            abort(403, 'This is not your quiz attempt.');
        }
    }
}
