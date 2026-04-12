<?php

namespace App\Http\Controllers\Facilitator;

use App\Http\Controllers\Controller;
use App\Models\InternshipEnrollment;
use App\Models\InternshipSubmission;
use App\Models\PortfolioItem;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InternshipOversightController extends Controller
{
    /**
     * Internship health overview for facilitator.
     */
    public function health(Request $request)
    {
        $user = $request->user();

        // Get enrollments where the facilitator oversees (via cohort or team lead)
        $enrollments = InternshipEnrollment::whereIn('status', ['enrolled', 'active'])
            ->with(['user', 'track', 'sponsor', 'tasks'])
            ->latest()
            ->paginate(20);

        // Compute health metrics
        $metrics = $enrollments->getCollection()->map(function ($enr) {
            $total = $enr->tasks->count();
            $done  = $enr->tasks->whereIn('status', ['done', 'approved'])->count();
            $overdue = $enr->tasks->filter(fn ($t) => $t->due_date && $t->due_date < now() && !in_array($t->status, ['done', 'approved']))->count();

            return [
                'enrollment_id' => $enr->id,
                'learner'       => $enr->user->first_name . ' ' . $enr->user->last_name,
                'track'         => $enr->track->title,
                'sponsor'       => $enr->sponsor ? $enr->sponsor->first_name . ' ' . $enr->sponsor->last_name : null,
                'progress_pct'  => $total > 0 ? round(($done / $total) * 100) : 0,
                'overdue_tasks' => $overdue,
                'overall_score' => $enr->overall_score,
                'status'        => $enr->status,
                'current_week'  => $enr->currentWeek(),
            ];
        });

        return Inertia::render('Facilitator/InternshipOversight/Health', [
            'enrollments' => $enrollments,
            'metrics'     => $metrics,
        ]);
    }

    /**
     * Portfolio completeness audit.
     */
    public function portfolios(Request $request)
    {
        $enrollments = InternshipEnrollment::whereIn('status', ['enrolled', 'active', 'completed'])
            ->with(['user', 'track'])
            ->get();

        $audit = $enrollments->map(function ($enr) {
            $count = PortfolioItem::forUser($enr->user_id)
                ->where('enrollment_id', $enr->id)
                ->count();

            return [
                'enrollment_id' => $enr->id,
                'learner'       => $enr->user->first_name . ' ' . $enr->user->last_name,
                'track'         => $enr->track->title,
                'portfolio_items_count' => $count,
                'status'        => $enr->status,
            ];
        });

        return Inertia::render('Facilitator/InternshipOversight/Portfolios', [
            'audit' => $audit,
        ]);
    }
}
