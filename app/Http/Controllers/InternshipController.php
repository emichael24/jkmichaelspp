<?php

namespace App\Http\Controllers;

use App\Models\InternshipEnrollment;
use App\Models\InternshipTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class InternshipController extends Controller
{
    /* ── Learner internship dashboard ───────────────── */

    public function dashboard()
    {
        $enrollment = InternshipEnrollment::with(['track', 'sponsor', 'teamLead'])
            ->forUser(Auth::id())
            ->active()
            ->first();

        if (!$enrollment) {
            return Inertia::render('Internship/Dashboard', [
                'enrollment' => null,
            ]);
        }

        $tasks = InternshipTask::where('enrollment_id', $enrollment->id)
            ->orderBy('sort_order')
            ->get();

        // Group tasks by status for kanban
        $kanban = [];
        foreach (InternshipTask::KANBAN_COLUMNS as $col) {
            $kanban[$col] = $tasks->where('status', $col)->values();
        }

        // Stats
        $totalTasks   = $tasks->count();
        $doneTasks    = $tasks->whereIn('status', ['approved', 'done'])->count();
        $overdueTasks = $tasks->filter(fn ($t) => $t->isOverdue())->count();
        $dueTodayCount = $tasks->filter(fn ($t) => $t->due_date && $t->due_date->isToday())->count();

        $milestones = $enrollment->track->milestones()->get();
        $currentWeek = $enrollment->currentWeek();

        return Inertia::render('Internship/Dashboard', [
            'enrollment'  => $enrollment,
            'kanban'      => $kanban,
            'stats'       => [
                'total'        => $totalTasks,
                'done'         => $doneTasks,
                'overdue'      => $overdueTasks,
                'due_today'    => $dueTodayCount,
                'progress_pct' => $enrollment->progressPct(),
            ],
            'milestones'   => $milestones,
            'current_week' => $currentWeek,
        ]);
    }

    /* ── Task board (kanban view) ───────────────────── */

    public function taskBoard(InternshipEnrollment $enrollment)
    {
        $this->authorizeEnrollment($enrollment);

        $tasks = InternshipTask::where('enrollment_id', $enrollment->id)
            ->with('milestone')
            ->orderBy('sort_order')
            ->get();

        $kanban = [];
        foreach (InternshipTask::KANBAN_COLUMNS as $col) {
            $kanban[$col] = $tasks->where('status', $col)->values();
        }

        return Inertia::render('Internship/TaskBoard', [
            'enrollment' => $enrollment->load('track'),
            'kanban'     => $kanban,
        ]);
    }

    /* ── Update task status (drag-drop) ────────────── */

    public function updateTaskStatus(Request $request, InternshipTask $task)
    {
        $enrollment = $task->enrollment;
        $this->authorizeEnrollment($enrollment);

        $validated = $request->validate([
            'status' => 'required|in:' . implode(',', InternshipTask::KANBAN_COLUMNS),
        ]);

        $task->update(['status' => $validated['status']]);

        return back()->with('success', 'Task updated.');
    }

    /* ── Auth helper ───────────────────────────────── */

    private function authorizeEnrollment(InternshipEnrollment $enrollment): void
    {
        $user = Auth::user();
        if ($enrollment->user_id !== $user->id && !$user->hasRole('platform_admin')) {
            abort(403, 'Unauthorized');
        }
    }
}
