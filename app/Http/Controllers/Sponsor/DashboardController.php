<?php

namespace App\Http\Controllers\Sponsor;

use App\Http\Controllers\Controller;
use App\Models\InternshipEnrollment;
use App\Models\InternshipSubmission;
use App\Models\InternshipTask;
use App\Models\Meeting;
use App\Models\SponsorReview;
use App\Models\SponsorScorecard;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $sponsorId = Auth::id();

        // Get active enrollments where this user is sponsor
        $enrollments = InternshipEnrollment::where('sponsor_id', $sponsorId)->whereHas('user', fn ($q) => $q->where('organization_id', Auth::user()->organization_id))
            ->with(['user:id,first_name,last_name,avatar', 'track:id,title,duration_weeks'])
            ->get();

        $activeEnrollments = $enrollments->where('status', 'active');
        $enrollmentIds = $enrollments->pluck('id');

        // Submissions pending review
        $pendingReviews = InternshipSubmission::whereHas('task', function ($q) use ($enrollmentIds) {
            $q->whereIn('enrollment_id', $enrollmentIds);
        })
            ->whereIn('status', ['submitted', 'in_review'])
            ->with(['task:id,enrollment_id,title,type,due_date', 'task.enrollment.user:id,first_name,last_name'])
            ->latest('submitted_at')
            ->limit(10)
            ->get();

        // Recent reviews done by this sponsor
        $recentReviews = SponsorReview::where('sponsor_id', $sponsorId)->whereHas('submission.task', fn ($q) => $q->whereIn('enrollment_id', $enrollmentIds))
            ->with(['submission.task:id,title,enrollment_id', 'submission.task.enrollment.user:id,first_name,last_name'])
            ->latest('reviewed_at')
            ->limit(5)
            ->get();

        // Task stats across all enrollments
        $totalTasks = InternshipTask::whereIn('enrollment_id', $enrollmentIds)->count();
        $completedTasks = InternshipTask::whereIn('enrollment_id', $enrollmentIds)->where('status', 'done')->count();
        $overdueTasks = InternshipTask::whereIn('enrollment_id', $enrollmentIds)
            ->where('due_date', '<', now())
            ->whereNotIn('status', ['done'])
            ->count();

        // Upcoming meetings
        $upcomingMeetings = Meeting::where('organizer_id', $sponsorId)
            ->where('scheduled_at', '>', now())
            ->orderBy('scheduled_at')
            ->limit(3)
            ->get()
            ->map(fn ($meeting) => [
                'id' => $meeting->id,
                'title' => $meeting->title,
                'scheduled_at' => $meeting->scheduled_at,
                'duration_minutes' => $meeting->duration_minutes,
                'meeting_url' => $meeting->meeting_url,
            ]);

        // Average scorecard score
        $avgScore = SponsorScorecard::where('sponsor_id', $sponsorId)->whereIn('enrollment_id', $enrollmentIds)->avg('overall_score');

        // Intern progress summaries
        $internSummaries = $activeEnrollments->map(function ($enrollment) {
            $tasksDone = $enrollment->tasks->where('status', 'done')->count();
            $tasksTotal = $enrollment->tasks->count();

            return [
                'id' => $enrollment->id,
                'intern' => $enrollment->user ? [
                    'name' => $enrollment->user->first_name.' '.$enrollment->user->last_name,
                    'avatar' => $enrollment->user->avatar,
                ] : null,
                'track' => $enrollment->track?->title,
                'progress_pct' => $enrollment->progress_pct ?? round($tasksTotal > 0 ? ($tasksDone / $tasksTotal) * 100 : 0),
                'status' => $enrollment->status,
                'current_week' => $enrollment->currentWeek(),
                'duration_weeks' => $enrollment->track?->duration_weeks,
                'overall_score' => $enrollment->overall_score,
            ];
        });

        return Inertia::render('Sponsor/Dashboard', [
            'stats' => [
                'activeInterns' => $activeEnrollments->count(),
                'pendingReviews' => $pendingReviews->count(),
                'completedTasks' => $completedTasks,
                'totalTasks' => $totalTasks,
                'overdueTasks' => $overdueTasks,
                'avgScore' => $avgScore ? round($avgScore, 1) : null,
                'totalReviews' => SponsorReview::where('sponsor_id', $sponsorId)->whereHas('submission.task', fn ($q) => $q->whereIn('enrollment_id', $enrollmentIds))->count(),
            ],
            'interns' => $internSummaries,
            'pendingReviews' => $pendingReviews,
            'recentReviews' => $recentReviews,
            'upcomingMeetings' => $upcomingMeetings,
        ]);
    }
}
