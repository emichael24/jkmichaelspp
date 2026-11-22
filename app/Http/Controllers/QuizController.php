<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\QuestionResponse;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\AssessmentAttemptService;
use App\Services\LearningAccessService;
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
        abort_unless($quiz->is_published, 404);
        $this->assertQuizAccess($quiz);
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
            'quiz' => $quiz,
            'previousAttempts' => $previousAttempts,
            'canAttempt' => $canAttempt,
            'attemptCount' => $attemptCount,
        ]);
    }

    /**
     * Start a new quiz attempt — loads the quiz player.
     */
    public function start(Quiz $quiz)
    {
        $user = Auth::user();
        abort_unless($quiz->is_published, 404);
        $this->assertQuizAccess($quiz);

        $attempt = app(AssessmentAttemptService::class)->start($quiz, $user, fn () => $quiz->getQuestionsForAttempt());

        if ($attempt->status === 'completed') {
            return redirect()->route('quiz.review', $attempt);
        }

        // Post/Redirect/Get: the player must live at a GET URL so answer saves
        // (which redirect back) and browser refreshes do not hit this POST route.
        return redirect()->route('quiz.play', $attempt);
    }

    /** Quiz player for an in-progress attempt. */
    public function play(QuizAttempt $attempt)
    {
        $this->authorize('own', $attempt);
        $attempt->load('quizzable');
        abort_unless($attempt->quizzable instanceof Quiz, 404);
        if ($attempt->status === 'completed') {
            return redirect()->route('quiz.review', $attempt);
        }
        $this->assertQuizAccess($attempt->quizzable);

        return $this->loadPlayer($attempt->quizzable, $attempt);
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
            'question_id' => 'required|exists:questions,id',
            'selected_answer_ids' => 'required|array|max:100',
            'selected_answer_ids.*' => 'string|max:1000',
            'confidence_level' => 'nullable|in:low,medium,high',
            'time_spent_seconds' => 'required|integer|min:0',
            'is_flagged' => 'boolean',
        ]);

        app(AssessmentAttemptService::class)->answer($attempt, $validated);

        return back()->with('answerSaved', true);
    }

    /**
     * Flag/unflag a question during the quiz.
     */
    public function flag(Request $request, QuizAttempt $attempt)
    {
        $this->authorize('own', $attempt);
        app(AssessmentAttemptService::class)->assertWritable($attempt);

        $request->validate([
            'question_id' => 'required|exists:questions,id',
            'is_flagged' => 'required|boolean',
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

        app(AssessmentAttemptService::class)->complete($attempt);

        return redirect()->route('quiz.review', $attempt->id)
            ->with('success', 'Quiz completed! Review your results below.');
    }

    /**
     * Show quiz review page with explanations.
     */
    public function review(QuizAttempt $attempt)
    {
        $this->authorize('own', $attempt);

        abort_unless($attempt->status === 'completed', 409, 'Complete the assessment before reviewing answers.');
        $attempt->load('quizzable');

        $review = $attempt->getReview();

        $quizTitle = $attempt->quizzable->title ?? 'Quiz';

        return Inertia::render('Quizzes/Review', [
            'attempt' => [
                'id' => $attempt->id,
                'status' => $attempt->status,
                'score_pct' => $attempt->score_pct,
                'correct_count' => $attempt->correct_count,
                'total_count' => $attempt->total_count,
                'time_spent_seconds' => $attempt->time_spent_seconds,
                'domain_scores' => $attempt->domain_scores,
                'started_at' => $attempt->started_at,
                'completed_at' => $attempt->completed_at,
                'passed' => $attempt->isPassed(),
                'passing_score' => $attempt->quizzable->passing_score_pct ?? 70,
            ],
            'quizTitle' => $quizTitle,
            'courseSlug' => $attempt->quizzable->course?->slug,
            'lessonUrl' => $attempt->quizzable instanceof Quiz && $attempt->quizzable->lesson_id && $attempt->quizzable->course
                ? route('lessons.show', ['course' => $attempt->quizzable->course->slug, 'lesson' => $attempt->quizzable->lesson_id])
                : null,
            'review' => $review,
        ]);
    }

    /** Lesson quizzes follow the lesson's release schedule and sequence rules. */
    private function assertQuizAccess(Quiz $quiz): void
    {
        $access = app(LearningAccessService::class);
        $access->assertCourse($quiz->course, Auth::user());
        if ($quiz->lesson_id && $quiz->lesson) {
            $access->assertLesson($quiz->lesson, Auth::user());
        }
    }

    /**
     * Load the quiz player Inertia page.
     */
    private function loadPlayer(Quiz $quiz, QuizAttempt $attempt, $questions = null)
    {
        $questions = app(AssessmentAttemptService::class)->questions($attempt);

        // Get existing responses for this attempt
        $existingResponses = $attempt->responses()
            ->get()
            ->keyBy('question_id')
            ->map(fn ($r) => [
                'selected_answer_ids' => $r->selected_answer_ids,
                'confidence_level' => $r->confidence_level,
                'time_spent_seconds' => $r->time_spent_seconds,
                'is_flagged' => $r->is_flagged,
            ]);

        // Sanitize questions — remove correct answers and explanations
        $sanitizedQuestions = $questions->map(fn (Question $q) => [
            'id' => $q->id,
            'uuid' => $q->uuid,
            'type' => $q->type,
            'stem' => $q->stem,
            'stem_media' => $q->stem_media,
            'options' => collect($q->options)->map(fn ($opt) => [
                'id' => $opt['id'],
                'text' => $opt['text'],
                'media_url' => $opt['media_url'] ?? null, 'match_text' => $opt['match_text'] ?? null,
            ])->toArray(),
            'domain' => $q->domain,
            'time_estimate_seconds' => $q->time_estimate_seconds,
        ]);

        return Inertia::render('Quizzes/Player', [
            'quiz' => [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'type' => $quiz->type,
                'question_count' => $quiz->question_count,
                'time_limit_minutes' => $quiz->time_limit_minutes,
                'passing_score_pct' => $quiz->passing_score_pct,
                'course_title' => $quiz->course?->title,
                'course_slug' => $quiz->course?->slug,
            ],
            'attempt' => [
                'id' => $attempt->id,
                'started_at' => $attempt->started_at,
                'expires_at' => $attempt->expires_at,
                'status' => $attempt->status,
            ],
            'questions' => $sanitizedQuestions->values(),
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
        abort_unless($attempt->quizzable_type === Quiz::class, 404);
        app(LearningAccessService::class)->assertCourse($attempt->quizzable->course, Auth::user());
    }
}
