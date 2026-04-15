<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\MockExam;
use App\Models\Question;
use App\Models\QuestionResponse;
use App\Models\QuizAttempt;
use App\Models\Lesson;
use App\Services\SpacedRepetitionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class MockExamController extends Controller
{
    /**
     * List available mock exams for a course.
     */
    public function index(Request $request)
    {
        $mocks = MockExam::with('course:id,title,slug')
            ->published()
            ->when($request->course_id, fn ($q, $c) => $q->where('course_id', $c))
            ->latest()
            ->paginate(12);

        $courses = Course::published()->orderBy('title')->get(['id', 'title', 'slug']);

        // Get user's best scores per mock
        $attemptScores = QuizAttempt::where('user_id', Auth::id())
            ->where('quizzable_type', MockExam::class)
            ->whereIn('quizzable_id', $mocks->pluck('id'))
            ->completed()
            ->selectRaw('quizzable_id, MAX(score_pct) as best_score, COUNT(*) as attempt_count')
            ->groupBy('quizzable_id')
            ->get()
            ->keyBy('quizzable_id');

        return Inertia::render('MockExams/Index', [
            'mocks'         => $mocks,
            'courses'       => $courses,
            'attemptScores' => $attemptScores,
            'filters'       => $request->only(['course_id']),
        ]);
    }

    /**
     * Show mock exam details/start page.
     */
    public function show(MockExam $mockExam)
    {
        $mockExam->load('course:id,title,slug');
        $user = Auth::user();

        $previousAttempts = $mockExam->attempts()
            ->where('user_id', $user->id)
            ->completed()
            ->latest('completed_at')
            ->limit(5)
            ->get(['id', 'score_pct', 'correct_count', 'total_count', 'time_spent_seconds', 'completed_at']);

        // Check for paused attempt
        $pausedAttempt = $mockExam->attempts()
            ->where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->first();

        return Inertia::render('MockExams/Show', [
            'mockExam'         => $mockExam,
            'previousAttempts' => $previousAttempts,
            'pausedAttempt'    => $pausedAttempt ? ['id' => $pausedAttempt->id, 'started_at' => $pausedAttempt->started_at] : null,
        ]);
    }

    /**
     * Start a new mock exam attempt.
     */
    public function start(MockExam $mockExam)
    {
        $user = Auth::user();

        // Check for existing in-progress attempt
        $existing = $mockExam->attempts()
            ->where('user_id', $user->id)
            ->inProgress()
            ->first();

        if ($existing) {
            return $this->loadPlayer($mockExam, $existing);
        }

        // Build questions based on domain distribution
        $questions = $this->buildQuestionSet($mockExam);

        // Create attempt
        $attempt = QuizAttempt::create([
            'user_id'        => $user->id,
            'quizzable_type' => MockExam::class,
            'quizzable_id'   => $mockExam->id,
            'status'         => 'in_progress',
            'total_count'    => $questions->count(),
            'started_at'     => now(),
        ]);

        $questions->each(fn (Question $q) => $q->incrementExposure());

        return $this->loadPlayer($mockExam, $attempt, $questions);
    }

    /**
     * Resume a paused attempt.
     */
    public function resume(QuizAttempt $attempt)
    {
        $this->authorizeOwn($attempt);

        if ($attempt->status !== 'in_progress') {
            return redirect()->route('mock-exams.show', $attempt->quizzable_id);
        }

        $mockExam = MockExam::findOrFail($attempt->quizzable_id);

        return $this->loadPlayer($mockExam, $attempt);
    }

    /**
     * Submit answer for a single question.
     */
    public function answer(Request $request, QuizAttempt $attempt)
    {
        $this->authorizeOwn($attempt);

        if ($attempt->status !== 'in_progress') {
            return back()->with('error', 'This attempt is no longer in progress.');
        }

        $validated = $request->validate([
            'question_id'          => 'required|exists:questions,id',
            'selected_answer_ids'  => 'required|array',
            'selected_answer_ids.*' => 'string',
            'confidence_level'     => 'nullable|in:low,medium,high',
            'time_spent_seconds'   => 'required|integer|min:0',
            'is_flagged'           => 'boolean',
        ]);

        $question = Question::findOrFail($validated['question_id']);
        $isCorrect = $question->isCorrect($validated['selected_answer_ids']);

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

        $question->updateStats($isCorrect, $validated['time_spent_seconds']);

        return back()->with('answerSaved', true);
    }

    /**
     * Complete the mock exam — calculate scores and generate report.
     */
    public function complete(QuizAttempt $attempt)
    {
        $this->authorizeOwn($attempt);

        if ($attempt->status !== 'in_progress') {
            return redirect()->route('mock-exams.report', $attempt->id);
        }

        $attempt->calculateScore();

        // Auto-add wrong answers to spaced repetition
        app(SpacedRepetitionService::class)->processCompletedAttempt($attempt);

        return redirect()->route('mock-exams.report', $attempt->id)
            ->with('success', 'Mock exam completed!');
    }

    /**
     * Full post-mock report.
     */
    public function report(QuizAttempt $attempt)
    {
        $this->authorizeOwn($attempt);

        $attempt->load('quizzable.course');

        $review = $attempt->getReview();

        // Generate 7-day plan
        $srService = app(SpacedRepetitionService::class);
        $improvementPlan = $srService->generate7DayPlan($attempt);

        // Time analysis
        $timeAnalysis = $this->buildTimeAnalysis($review);

        // Confidence analysis
        $confidenceAnalysis = $this->buildConfidenceAnalysis($review);

        // Weakness clusters
        $weaknessClusters = $this->buildWeaknessClusters($review);

        return Inertia::render('MockExams/Report', [
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
            'mockExam'           => $attempt->quizzable,
            'review'             => $review,
            'improvementPlan'    => $improvementPlan,
            'timeAnalysis'       => $timeAnalysis,
            'confidenceAnalysis' => $confidenceAnalysis,
            'weaknessClusters'   => $weaknessClusters,
        ]);
    }

    /**
     * Build question set from domain/difficulty distribution.
     */
    private function buildQuestionSet(MockExam $mockExam): \Illuminate\Support\Collection
    {
        $query = Question::forCourse($mockExam->course_id)->published();

        // If domain distribution is specified, build proportionally
        $distribution = $mockExam->domain_distribution;
        if (is_string($distribution)) {
            $distribution = json_decode($distribution, true);
        }

        if (is_array($distribution) && !empty($distribution)) {
            $questions = collect();

            foreach ($distribution as $domain => $count) {
                $domainQuestions = (clone $query)
                    ->byDomain($domain)
                    ->inRandomOrder()
                    ->limit($count)
                    ->get();
                $questions = $questions->merge($domainQuestions);
            }

            // Fill remaining with random if needed
            $remaining = $mockExam->question_count - $questions->count();
            if ($remaining > 0) {
                $extraQuestions = (clone $query)
                    ->whereNotIn('id', $questions->pluck('id'))
                    ->inRandomOrder()
                    ->limit($remaining)
                    ->get();
                $questions = $questions->merge($extraQuestions);
            }

            return $questions->shuffle();
        }

        // Default: random selection
        return $query->inRandomOrder()->limit($mockExam->question_count)->get();
    }

    /**
     * Load the mock exam player.
     */
    private function loadPlayer(MockExam $mockExam, QuizAttempt $attempt, $questions = null)
    {
        if (! $questions) {
            // Re-derive questions from responses + remaining pool
            $answeredIds = $attempt->responses()->pluck('question_id');
            $questions = Question::forCourse($mockExam->course_id)
                ->published()
                ->inRandomOrder()
                ->limit($mockExam->question_count)
                ->get();
        }

        $existingResponses = $attempt->responses()
            ->get()
            ->keyBy('question_id')
            ->map(fn ($r) => [
                'selected_answer_ids' => $r->selected_answer_ids,
                'confidence_level'    => $r->confidence_level,
                'time_spent_seconds'  => $r->time_spent_seconds,
                'is_flagged'          => $r->is_flagged,
            ]);

        // Strip correct answers
        $sanitized = $questions->map(fn (Question $q) => [
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

        return Inertia::render('MockExams/Player', [
            'mockExam' => [
                'id'                 => $mockExam->id,
                'title'              => $mockExam->title,
                'type'               => $mockExam->type,
                'question_count'     => $mockExam->question_count,
                'time_limit_minutes' => $mockExam->time_limit_minutes,
                'passing_score_pct'  => $mockExam->passing_score_pct,
                'allow_pause'        => $mockExam->allow_pause,
                'is_exam_simulation' => $mockExam->is_exam_simulation,
                'course_title'       => $mockExam->course?->title,
                'course_slug'        => $mockExam->course?->slug,
            ],
            'attempt' => [
                'id'         => $attempt->id,
                'started_at' => $attempt->started_at,
                'status'     => $attempt->status,
            ],
            'questions'         => $sanitized->values(),
            'existingResponses' => $existingResponses,
        ]);
    }

    /**
     * Build time-per-question analysis.
     */
    private function buildTimeAnalysis(array $review): array
    {
        $items = collect($review);

        $avgTime = $items->avg('time_spent_seconds') ?: 0;

        $fastest = $items->sortBy('time_spent_seconds')->take(5)->map(fn ($r) => [
            'stem'    => \Illuminate\Support\Str::limit($r['stem'], 80),
            'time'    => $r['time_spent_seconds'],
            'correct' => $r['is_correct'],
        ])->values()->toArray();

        $slowest = $items->sortByDesc('time_spent_seconds')->take(5)->map(fn ($r) => [
            'stem'    => \Illuminate\Support\Str::limit($r['stem'], 80),
            'time'    => $r['time_spent_seconds'],
            'correct' => $r['is_correct'],
        ])->values()->toArray();

        // Time by domain
        $byDomain = $items->groupBy('domain')->map(fn ($group, $domain) => [
            'domain'   => $domain,
            'avg_time' => round($group->avg('time_spent_seconds')),
            'count'    => $group->count(),
        ])->values()->toArray();

        return [
            'avg_time_seconds' => round($avgTime),
            'fastest'          => $fastest,
            'slowest'          => $slowest,
            'by_domain'        => $byDomain,
        ];
    }

    /**
     * Build confidence analysis (mismatch detection).
     */
    private function buildConfidenceAnalysis(array $review): array
    {
        $items = collect($review)->filter(fn ($r) => $r['confidence_level'] !== null);

        $mismatch = $items->filter(fn ($r) =>
            ($r['confidence_level'] === 'high' && ! $r['is_correct']) ||
            ($r['confidence_level'] === 'low' && $r['is_correct'])
        );

        $overconfident = $items->filter(fn ($r) =>
            $r['confidence_level'] === 'high' && ! $r['is_correct']
        )->count();

        $underconfident = $items->filter(fn ($r) =>
            $r['confidence_level'] === 'low' && $r['is_correct']
        )->count();

        $byConfidence = $items->groupBy('confidence_level')->map(fn ($group, $level) => [
            'level'       => $level,
            'total'       => $group->count(),
            'correct'     => $group->where('is_correct', true)->count(),
            'accuracy_pct' => $group->count() > 0
                ? round($group->where('is_correct', true)->count() / $group->count() * 100)
                : 0,
        ])->values()->toArray();

        return [
            'total_rated'     => $items->count(),
            'overconfident'   => $overconfident,
            'underconfident'  => $underconfident,
            'mismatch_count'  => $mismatch->count(),
            'by_confidence'   => $byConfidence,
        ];
    }

    /**
     * Build weakness clusters (domain/subtopic/difficulty) with lesson recommendations.
     */
    private function buildWeaknessClusters(array $review): array
    {
        $items = collect($review);
        if ($items->isEmpty()) {
            return [
                'clusters' => [],
                'difficulty_breakdown' => [],
            ];
        }

        $lessonIds = $items->whereNotNull('linked_lesson_id')
            ->pluck('linked_lesson_id')
            ->unique()
            ->values();

        $lessons = Lesson::whereIn('id', $lessonIds)
            ->get(['id', 'title', 'slug'])
            ->keyBy('id');

        $groups = $items->groupBy(function ($r) {
            $domain = $r['domain'] ?: 'General';
            $subtopic = $r['subtopic'] ?: 'Core';
            return $domain . '|' . $subtopic;
        });

        $clusters = $groups->map(function ($group, $key) use ($lessons) {
            [$domain, $subtopic] = explode('|', $key, 2);
            $total = $group->count();
            $wrong = $group->where('is_correct', false)->count();
            $accuracy = $total > 0 ? round((($total - $wrong) / $total) * 100) : 0;

            $difficultyBreakdown = $group->groupBy(fn ($r) => $r['difficulty'] ?: 'unspecified')
                ->map(fn ($items, $difficulty) => [
                    'difficulty' => $difficulty,
                    'wrong'      => $items->where('is_correct', false)->count(),
                    'total'      => $items->count(),
                ])->values()->toArray();

            $lessonRecommendations = $group->where('is_correct', false)
                ->pluck('linked_lesson_id')
                ->filter()
                ->unique()
                ->map(fn ($id) => $lessons->get($id))
                ->filter()
                ->take(3)
                ->map(fn ($lesson) => [
                    'id'    => $lesson->id,
                    'title' => $lesson->title,
                    'slug'  => $lesson->slug,
                ])->values()->toArray();

            return [
                'domain'       => $domain,
                'subtopic'     => $subtopic,
                'wrong_count'  => $wrong,
                'total_count'  => $total,
                'accuracy_pct' => $accuracy,
                'difficulty_breakdown' => $difficultyBreakdown,
                'lessons'      => $lessonRecommendations,
            ];
        })->values();

        $clusters = $clusters->sortByDesc('wrong_count')->values()->take(6);

        $difficultySummary = $items->groupBy(fn ($r) => $r['difficulty'] ?: 'unspecified')
            ->map(fn ($group, $difficulty) => [
                'difficulty' => $difficulty,
                'wrong'      => $group->where('is_correct', false)->count(),
                'total'      => $group->count(),
                'accuracy_pct' => $group->count() > 0
                    ? round($group->where('is_correct', true)->count() / $group->count() * 100)
                    : 0,
            ])->values()->toArray();

        return [
            'clusters' => $clusters,
            'difficulty_breakdown' => $difficultySummary,
        ];
    }

    private function authorizeOwn(QuizAttempt $attempt): void
    {
        if ($attempt->user_id !== Auth::id()) {
            abort(403, 'This is not your attempt.');
        }
    }
}
