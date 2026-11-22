<?php

namespace App\Http\Controllers\Sme;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Question;
use App\Models\QuestionFlag;
use App\Services\ContentWorkflowService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class QuestionManagementController extends Controller
{
    public function __construct(
        private ContentWorkflowService $workflowService
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Question Bank with Analytics
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $questions = Question::with('course:id,title')
            ->withCount('responses')
            ->when($request->search, fn ($q, $s) => $q->where('stem', 'like', "%{$s}%"))
            ->when($request->course_id, fn ($q, $c) => $q->forCourse($c))
            ->when($request->domain, fn ($q, $d) => $q->byDomain($d))
            ->when($request->difficulty, fn ($q, $d) => $q->byDifficulty($d))
            ->when($request->type, fn ($q, $t) => $q->byType($t))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->sort === 'exposure', fn ($q) => $q->orderByDesc('exposure_count'))
            ->when($request->sort === 'difficulty', fn ($q) => $q->orderBy('correct_rate'))
            ->when($request->sort === 'discrimination', fn ($q) => $q->orderByDesc('discrimination_index'))
            ->when(! $request->sort, fn ($q) => $q->latest())
            ->paginate(20)
            ->withQueryString();

        $courses = Course::orderBy('title')->get(['id', 'title']);

        $stats = [
            'total' => Question::count(),
            'published' => Question::published()->count(),
            'draft' => Question::where('status', 'draft')->count(),
            'flagged' => QuestionFlag::unresolved()->count(),
            'high_exposure' => Question::where('exposure_count', '>', 100)->count(),
            'low_discrimination' => Question::where('discrimination_index', '<', 0.2)
                ->where('discrimination_index', '>', 0)
                ->count(),
        ];

        $domains = Question::select('domain')
            ->distinct()
            ->whereNotNull('domain')
            ->orderBy('domain')
            ->pluck('domain');

        return Inertia::render('Sme/QuestionBank', [
            'questions' => $questions,
            'courses' => $courses,
            'domains' => $domains,
            'stats' => $stats,
            'filters' => $request->only(['search', 'course_id', 'domain', 'difficulty', 'type', 'status', 'sort']),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Item Analytics Detail
    |--------------------------------------------------------------------------
    */

    public function analytics(Question $question)
    {
        $question->load('course:id,title');

        $analytics = $this->workflowService->getQuestionAnalytics($question->id);

        return Inertia::render('Sme/ItemAnalytics', [
            'question' => $question,
            'analytics' => $analytics,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Blueprint Coverage
    |--------------------------------------------------------------------------
    */

    public function blueprintCoverage(Request $request)
    {
        $courses = Course::whereNotNull('exam_blueprint')
            ->orderBy('title')
            ->get(['id', 'title', 'exam_blueprint']);

        $courseId = $request->get('course_id', $courses->first()?->id);
        $coverage = $courseId ? $this->workflowService->getBlueprintCoverage($courseId) : [];

        return Inertia::render('Sme/BlueprintCoverage', [
            'courses' => $courses,
            'coverage' => $coverage,
            'filters' => ['course_id' => $courseId],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Flag Queue
    |--------------------------------------------------------------------------
    */

    public function flagQueue(Request $request)
    {
        $flags = QuestionFlag::with(['question.course:id,title', 'flagger:id,first_name,last_name', 'resolver:id,first_name,last_name'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->unresolved())
            ->when($request->reason, fn ($q, $r) => $q->byReason($r))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'open' => QuestionFlag::open()->count(),
            'in_review' => QuestionFlag::inReview()->count(),
            'resolved' => QuestionFlag::resolved()->count(),
            'dismissed' => QuestionFlag::where('status', 'dismissed')->count(),
        ];

        return Inertia::render('Sme/FlagQueue', [
            'flags' => $flags,
            'stats' => $stats,
            'filters' => $request->only(['status', 'reason']),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve Flag
    |--------------------------------------------------------------------------
    */

    public function resolveFlag(Request $request, QuestionFlag $flag)
    {
        $request->validate([
            'status' => 'required|in:resolved,dismissed',
            'resolution_notes' => 'required|string|max:2000',
        ]);

        if ($request->status === 'dismissed') {
            $flag->dismiss($request->user(), $request->resolution_notes);
        } else {
            $flag->resolve($request->user(), $request->resolution_notes);
        }

        return back()->with('success', 'Flag resolved.');
    }

    /*
    |--------------------------------------------------------------------------
    | Bulk Analytics Dashboard
    |--------------------------------------------------------------------------
    */

    public function analyticsDashboard(Request $request)
    {
        $courseId = $request->get('course_id');

        $query = Question::query();
        if ($courseId) {
            $query->where('course_id', $courseId);
        }

        // Difficulty distribution
        $difficultyDist = [
            'easy' => (clone $query)->where('correct_rate', '>=', 0.8)->count(),
            'medium' => (clone $query)->where('correct_rate', '>=', 0.4)->where('correct_rate', '<', 0.8)->count(),
            'hard' => (clone $query)->where('correct_rate', '<', 0.4)->where('correct_rate', '>', 0)->count(),
            'no_data' => (clone $query)->where(function ($q) {
                $q->whereNull('correct_rate')->orWhere('correct_rate', 0);
            })->count(),
        ];

        // Discrimination distribution
        $discriminationDist = [
            'excellent' => (clone $query)->where('discrimination_index', '>=', 0.4)->count(),
            'good' => (clone $query)->where('discrimination_index', '>=', 0.3)->where('discrimination_index', '<', 0.4)->count(),
            'fair' => (clone $query)->where('discrimination_index', '>=', 0.2)->where('discrimination_index', '<', 0.3)->count(),
            'poor' => (clone $query)->where('discrimination_index', '<', 0.2)->where('discrimination_index', '>', 0)->count(),
            'no_data' => (clone $query)->where(function ($q) {
                $q->whereNull('discrimination_index')->orWhere('discrimination_index', 0);
            })->count(),
        ];

        // Avg time per difficulty
        $avgTimeByDifficulty = Question::when($courseId, fn ($q) => $q->where('course_id', $courseId))
            ->whereNotNull('avg_time_seconds')
            ->selectRaw('difficulty, AVG(avg_time_seconds) as avg_time')
            ->groupBy('difficulty')
            ->pluck('avg_time', 'difficulty');

        $courses = Course::orderBy('title')->get(['id', 'title']);

        return Inertia::render('Sme/AnalyticsDashboard', [
            'difficultyDist' => $difficultyDist,
            'discriminationDist' => $discriminationDist,
            'avgTimeByDifficulty' => $avgTimeByDifficulty,
            'courses' => $courses,
            'filters' => ['course_id' => $courseId],
        ]);
    }
}
