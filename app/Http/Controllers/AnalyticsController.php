<?php

namespace App\Http\Controllers;

use App\Services\ErrorPatternService;
use App\Services\MasteryService;
use App\Models\QuizAttempt;
use App\Models\ReadinessScore;
use App\Models\UserMastery;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AnalyticsController extends Controller
{
    public function __construct(
        private MasteryService $masteryService,
        private ErrorPatternService $errorPatternService,
    ) {}

    /**
     * Analytics dashboard — mastery map, trends, error patterns, weekly report.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $courseId = $request->integer('course_id') ?: null;

        // Mastery map data
        $masteryMap = $this->masteryService->getMasteryMap($user->id, $courseId);

        // Readiness score
        $readiness = $courseId
            ? ReadinessScore::latestFor($user->id, $courseId)
            : ReadinessScore::forUser($user->id)->latest()->first();

        // Performance trends (quiz scores over time)
        $trendsQuery = QuizAttempt::forUser($user->id)->completed()->orderBy('completed_at');
        if ($courseId) {
            $trendsQuery->whereHasMorph('quizzable', ['*'], fn ($q) => $q->where('course_id', $courseId));
        }
        $trends = $trendsQuery->limit(30)->get(['id', 'score_pct', 'completed_at', 'quizzable_type']);

        $trendData = $trends->map(fn ($t) => [
            'date'     => $t->completed_at?->format('M d'),
            'score'    => $t->score_pct,
            'type'     => str_contains($t->quizzable_type, 'Mock') ? 'mock' : 'quiz',
        ]);

        // Mock exam history
        $mockHistory = QuizAttempt::forUser($user->id)
            ->completed()
            ->whereHasMorph('quizzable', [\App\Models\MockExam::class])
            ->with('quizzable:id,title,passing_score_pct')
            ->orderByDesc('completed_at')
            ->limit(10)
            ->get()
            ->map(fn ($a) => [
                'id'            => $a->id,
                'title'         => $a->quizzable?->title ?? '—',
                'score'         => $a->score_pct,
                'passed'        => $a->isPassed(),
                'time_spent'    => $a->time_spent_seconds,
                'completed_at'  => $a->completed_at?->format('M d, Y'),
            ]);

        // Error patterns
        $errorPatterns = $this->errorPatternService->getPatterns($user->id, $courseId)->map(fn ($p) => [
            'type'             => $p->pattern_type,
            'domain'           => $p->domain,
            'occurrence_count' => $p->occurrence_count,
            'suggested_fix'    => $p->suggested_fix,
            'detected_at'      => $p->detected_at?->diffForHumans(),
        ]);

        // Coach insight
        $coachInsight = $this->errorPatternService->generateCoachInsight($user->id, $courseId);

        // User's enrolled courses for filtering
        $enrolledCourses = $user->enrollments()
            ->where('status', 'active')
            ->with('course:id,title')
            ->get()
            ->map(fn ($e) => ['id' => $e->course->id, 'title' => $e->course->title]);

        return Inertia::render('Analytics/Dashboard', [
            'masteryMap'      => $masteryMap,
            'readiness'       => $readiness ? [
                'score'           => $readiness->score_pct,
                'confidence_band' => $readiness->confidence_band,
                'pass_likelihood' => $readiness->pass_likelihood,
                'domain_breakdown' => $readiness->domain_breakdown,
                'weak_areas'      => $readiness->weak_areas,
                'calculated_at'   => $readiness->calculated_at?->diffForHumans(),
            ] : null,
            'trends'          => $trendData,
            'mockHistory'     => $mockHistory,
            'errorPatterns'   => $errorPatterns,
            'coachInsight'    => $coachInsight,
            'enrolledCourses' => $enrolledCourses,
            'selectedCourse'  => $courseId,
        ]);
    }

    /**
     * Weekly report view.
     */
    public function weeklyReport(Request $request)
    {
        $report = $this->masteryService->generateWeeklyReport($request->user()->id);

        return Inertia::render('Analytics/WeeklyReport', [
            'report' => $report,
        ]);
    }

    /**
     * API: Mastery data for a specific course (for radar chart).
     */
    public function mastery(Request $request)
    {
        $courseId = $request->integer('course_id');
        $map = $this->masteryService->getMasteryMap($request->user()->id, $courseId ?: null);

        return response()->json($map);
    }
}
