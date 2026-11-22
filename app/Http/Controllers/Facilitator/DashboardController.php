<?php

namespace App\Http\Controllers\Facilitator;

use App\Http\Controllers\Controller;
use App\Models\AssignmentSubmission;
use App\Models\Cohort;
use App\Models\CohortMember;
use App\Models\DailyActivityLog;
use App\Models\Intervention;
use App\Models\Meeting;
use App\Services\CohortHealthService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(private CohortHealthService $healthService) {}

    public function __invoke()
    {
        $facilitatorId = Auth::id();

        // ── Active cohorts with basic stats ──────────
        $cohorts = Cohort::forFacilitator($facilitatorId)
            ->with('course:id,title,slug,thumbnail')
            ->withCount(['members' => fn ($q) => $q->active()->learners()])
            ->latest()
            ->limit(10)
            ->get();

        // Compute health for each cohort
        $cohortSummaries = $cohorts->map(function ($cohort) {
            $health = $this->healthService->healthScore($cohort);
            $atRisk = $this->healthService->atRiskLearners($cohort);

            return [
                'id' => $cohort->id,
                'name' => $cohort->name,
                'status' => $cohort->status,
                'course' => $cohort->course?->title,
                'thumbnail' => $cohort->course?->thumbnail,
                'learner_count' => $cohort->members_count,
                'start_date' => $cohort->start_date->format('M d, Y'),
                'end_date' => $cohort->end_date->format('M d, Y'),
                'health_score' => $health['score'],
                'at_risk_count' => count($atRisk),
            ];
        });

        $totalAtRisk = $cohortSummaries->sum('at_risk_count');

        // ── Quick stats ──────────────────────────────
        $totalLearners = Cohort::forFacilitator($facilitatorId)
            ->active()
            ->withCount(['members' => fn ($q) => $q->active()->learners()])
            ->get()
            ->sum('members_count');

        // ── Recent interventions ─────────────────────
        $recentInterventions = Intervention::where('facilitator_id', $facilitatorId)
            ->with('learner:id,first_name,last_name')
            ->latest()
            ->limit(5)
            ->get();

        // ── Pending grading queue ────────────────────
        $cohortIds = $cohorts->pluck('id');
        $learnerIds = CohortMember::whereIn('cohort_id', $cohortIds)
            ->active()->learners()
            ->pluck('user_id');

        $pendingSubmissions = AssignmentSubmission::pending()
            ->whereIn('user_id', $learnerIds)
            ->with(['user:id,first_name,last_name', 'assignment:id,title,max_score'])
            ->latest('submitted_at')
            ->limit(8)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'learner' => $s->user ? ($s->user->first_name.' '.$s->user->last_name) : 'Unknown',
                'assignment' => $s->assignment?->title ?? 'Untitled',
                'submitted' => $s->submitted_at?->diffForHumans(),
                'status' => $s->status,
            ]);

        // ── Upcoming sessions ────────────────────────
        $upcomingSessions = Meeting::forOrganizer($facilitatorId)
            ->upcoming()
            ->limit(5)
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'title' => $m->title,
                'date' => $m->scheduled_at->format('M d'),
                'time' => $m->scheduled_at->format('h:i A'),
                'duration' => $m->duration_minutes,
                'platform' => $m->platform,
                'url' => $m->meeting_url,
                'attendees' => $m->attendees()->count(),
            ]);

        // ── Weekly engagement summary ────────────────
        $weekStart = now()->startOfWeek();
        $weekActivity = DailyActivityLog::whereIn('user_id', $learnerIds)
            ->where('date', '>=', $weekStart)
            ->get();

        $engagementSummary = [
            'activeLearners' => $weekActivity->pluck('user_id')->unique()->count(),
            'totalMinutes' => $weekActivity->sum('time_spent_minutes'),
            'lessonsCompleted' => $weekActivity->sum('lessons_completed'),
            'quizzesTaken' => $weekActivity->sum('quizzes_completed'),
            'avgAccuracy' => $weekActivity->sum('questions_answered') > 0
                ? round(($weekActivity->sum('questions_correct') / $weekActivity->sum('questions_answered')) * 100)
                : 0,
        ];

        // ── Engagement heatmap (last 4 weeks) ───────
        $fourWeeksAgo = now()->subWeeks(4)->startOfWeek();
        $engagementHeatmap = DailyActivityLog::whereIn('user_id', $learnerIds)
            ->where('date', '>=', $fourWeeksAgo)
            ->select('user_id', 'date', DB::raw('CASE WHEN time_spent_minutes > 0 THEN 1 ELSE 0 END as active'))
            ->get()
            ->groupBy('user_id')
            ->map(fn ($logs) => $logs->pluck('active', 'date')->toArray())
            ->take(20); // Limit for performance

        return Inertia::render('Facilitator/Dashboard', [
            'cohorts' => $cohortSummaries,
            'totalLearners' => $totalLearners,
            'totalAtRisk' => $totalAtRisk,
            'activeCohortCount' => $cohorts->where('status', 'active')->count(),
            'recentInterventions' => $recentInterventions,
            'pendingSubmissions' => $pendingSubmissions,
            'upcomingSessions' => $upcomingSessions,
            'engagementSummary' => $engagementSummary,
        ]);
    }
}
