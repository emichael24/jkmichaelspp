<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InternshipEnrollment;
use App\Models\InternshipMilestone;
use App\Models\InternshipTask;
use App\Models\InternshipTrack;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class InternshipTrackController extends Controller
{
    /* ── List tracks ───────────────────────────────── */

    public function index(Request $request)
    {
        $tracks = InternshipTrack::withCount(['enrollments', 'milestones'])
            ->when($request->search, fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->orderBy('title')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Internships/Index', [
            'tracks'  => $tracks,
            'filters' => $request->only('search'),
        ]);
    }

    /* ── Create form ───────────────────────────────── */

    public function create()
    {
        return Inertia::render('Admin/Internships/Create');
    }

    /* ── Store ─────────────────────────────────────── */

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'description'      => 'nullable|string',
            'duration_weeks'   => 'required|integer|min:1|max:52',
            'price'            => 'required|numeric|min:0',
            'currency'         => 'string|size:3',
            'tools_required'   => 'nullable|array',
            'expectations'     => 'nullable|string',
            'skills_covered'   => 'nullable|array',
            'max_participants' => 'integer|min:1|max:500',
        ]);

        $validated['slug'] = Str::slug($validated['title']);

        $track = InternshipTrack::create($validated);

        return redirect()->route('admin.internships.show', $track)
            ->with('success', 'Internship track created.');
    }

    /* ── Show / manage one track ───────────────────── */

    public function show(InternshipTrack $track)
    {
        $track->load('milestones');

        $enrollments = InternshipEnrollment::where('track_id', $track->id)
            ->with(['user', 'sponsor'])
            ->withCount(['tasks', 'tasks as done_tasks_count' => fn ($q) => $q->whereIn('status', ['approved', 'done'])])
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Admin/Internships/Show', [
            'track'       => $track,
            'enrollments' => $enrollments,
        ]);
    }

    /* ── Update ────────────────────────────────────── */

    public function update(Request $request, InternshipTrack $track)
    {
        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'description'      => 'nullable|string',
            'duration_weeks'   => 'required|integer|min:1|max:52',
            'price'            => 'required|numeric|min:0',
            'currency'         => 'string|size:3',
            'tools_required'   => 'nullable|array',
            'expectations'     => 'nullable|string',
            'skills_covered'   => 'nullable|array',
            'max_participants' => 'integer|min:1|max:500',
            'is_published'     => 'boolean',
        ]);

        $track->update($validated);

        return back()->with('success', 'Track updated.');
    }

    /* ── Destroy ───────────────────────────────────── */

    public function destroy(InternshipTrack $track)
    {
        $track->delete();

        return redirect()->route('admin.internships.index')
            ->with('success', 'Track deleted.');
    }

    /* ── Milestones management ─────────────────────── */

    public function storeMilestone(Request $request, InternshipTrack $track)
    {
        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string',
            'week_number'  => 'required|integer|min:1',
            'deliverables' => 'nullable|array',
        ]);

        $validated['sort_order'] = $track->milestones()->count();

        $track->milestones()->create($validated);

        return back()->with('success', 'Milestone added.');
    }

    public function destroyMilestone(InternshipTrack $track, InternshipMilestone $milestone)
    {
        $milestone->delete();

        return back()->with('success', 'Milestone removed.');
    }

    /* ── Enrollment management ─────────────────────── */

    public function storeEnrollment(Request $request, InternshipTrack $track)
    {
        $validated = $request->validate([
            'user_id'       => 'required|exists:users,id',
            'sponsor_id'    => 'nullable|exists:users,id',
            'project_title' => 'nullable|string|max:255',
            'start_date'    => 'required|date',
            'end_date'      => 'required|date|after:start_date',
        ]);

        $validated['track_id'] = $track->id;

        InternshipEnrollment::create($validated);

        return back()->with('success', 'Learner enrolled.');
    }

    public function updateEnrollment(Request $request, InternshipEnrollment $enrollment)
    {
        $validated = $request->validate([
            'status'        => 'in:enrolled,active,completed,withdrawn,failed',
            'sponsor_id'    => 'nullable|exists:users,id',
            'project_title' => 'nullable|string|max:255',
        ]);

        $enrollment->update($validated);

        return back()->with('success', 'Enrollment updated.');
    }

    /* ── Enrollment Task management ────────────────── */

    public function enrollmentTasks(InternshipEnrollment $enrollment)
    {
        $enrollment->load(['user', 'track.milestones', 'tasks' => fn ($q) => $q->orderBy('sort_order')]);

        return Inertia::render('Admin/Internships/TaskBoard', [
            'enrollment' => $enrollment,
        ]);
    }

    public function storeTask(Request $request, InternshipEnrollment $enrollment)
    {
        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string',
            'type'         => 'required|in:daily,weekly,monthly',
            'status'       => 'required|in:backlog,this_week,today,in_progress,in_review,approved,needs_revision,done',
            'priority'     => 'required|in:low,medium,high,urgent',
            'milestone_id' => 'nullable|exists:internship_milestones,id',
            'due_date'     => 'nullable|date',
        ]);

        $validated['enrollment_id'] = $enrollment->id;
        $validated['assigned_to']   = $enrollment->user_id;
        $validated['sort_order']    = InternshipTask::where('enrollment_id', $enrollment->id)->count();

        InternshipTask::create($validated);

        return back()->with('success', 'Task added to intern\'s board.');
    }

    public function updateTask(Request $request, InternshipTask $task)
    {
        $validated = $request->validate([
            'title'        => 'sometimes|required|string|max:255',
            'description'  => 'nullable|string',
            'type'         => 'sometimes|in:daily,weekly,monthly',
            'status'       => 'sometimes|in:backlog,this_week,today,in_progress,in_review,approved,needs_revision,done',
            'priority'     => 'sometimes|in:low,medium,high,urgent',
            'milestone_id' => 'nullable|exists:internship_milestones,id',
            'due_date'     => 'nullable|date',
        ]);

        $task->update($validated);

        return back()->with('success', 'Task updated.');
    }

    public function destroyTask(InternshipTask $task)
    {
        $task->delete();

        return back()->with('success', 'Task deleted.');
    }
}
