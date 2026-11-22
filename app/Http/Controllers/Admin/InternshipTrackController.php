<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InternshipEnrollment;
use App\Models\InternshipMilestone;
use App\Models\InternshipTask;
use App\Models\InternshipTrack;
use App\Models\User;
use App\Services\CertificateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            'tracks' => $tracks,
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
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'duration_weeks' => 'required|integer|min:1|max:52',
            'price' => 'required|numeric|min:0',
            'currency' => 'string|size:3',
            'tools_required' => 'nullable|array',
            'expectations' => 'nullable|string',
            'skills_covered' => 'nullable|array',
            'review_rubric' => 'nullable|array|min:1|max:20',
            'review_rubric.*' => ['required', 'string', 'distinct:ignore_case', 'max:80', 'regex:/^[\pL\pN -]+$/u'],
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
            'track' => $track,
            'enrollments' => $enrollments,
        ]);
    }

    /* ── Update ────────────────────────────────────── */

    public function update(Request $request, InternshipTrack $track)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'duration_weeks' => 'required|integer|min:1|max:52',
            'price' => 'required|numeric|min:0',
            'currency' => 'string|size:3',
            'tools_required' => 'nullable|array',
            'expectations' => 'nullable|string',
            'skills_covered' => 'nullable|array',
            'review_rubric' => 'nullable|array|min:1|max:20',
            'review_rubric.*' => ['required', 'string', 'distinct:ignore_case', 'max:80', 'regex:/^[\pL\pN -]+$/u'],
            'max_participants' => 'integer|min:1|max:500',
            'is_published' => 'boolean',
        ]);

        $track->update($validated);

        return back()->with('success', 'Track updated.');
    }

    /* ── Destroy ───────────────────────────────────── */

    public function destroy(InternshipTrack $track)
    {
        DB::transaction(function () use ($track) {
            $track = InternshipTrack::whereKey($track->id)->lockForUpdate()->firstOrFail();
            abort_if($track->enrollments()->exists(), 409, 'Tracks with enrollments must be retained. Unpublish this track to stop new registrations.');
            $track->delete();
        });

        return redirect()->route('admin.internships.index')
            ->with('success', 'Track deleted.');
    }

    /* ── Milestones management ─────────────────────── */

    public function storeMilestone(Request $request, InternshipTrack $track)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'week_number' => 'required|integer|min:1',
            'deliverables' => 'nullable|array',
        ]);

        $validated['sort_order'] = $track->milestones()->count();

        $track->milestones()->create($validated);

        return back()->with('success', 'Milestone added.');
    }

    public function destroyMilestone(InternshipTrack $track, InternshipMilestone $milestone)
    {
        abort_unless($milestone->track_id === $track->id, 404);
        DB::transaction(function () use ($track, $milestone) {
            $track = InternshipTrack::whereKey($track->id)->lockForUpdate()->firstOrFail();
            abort_if($track->enrollments()->exists(), 409, 'Milestones in an assigned track must be retained.');
            $milestone->delete();
        });

        return back()->with('success', 'Milestone removed.');
    }

    /* ── Enrollment management ─────────────────────── */

    public function storeEnrollment(Request $request, InternshipTrack $track)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'sponsor_id' => 'nullable|exists:users,id',
            'project_title' => 'nullable|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        $validated['track_id'] = $track->id;

        DB::transaction(function () use ($track, $validated) {
            $learner = User::whereKey($validated['user_id'])->where('is_active', true)->lockForUpdate()->firstOrFail();
            $track = InternshipTrack::whereKey($track->id)->lockForUpdate()->firstOrFail();
            $this->validateSponsor($validated['sponsor_id'] ?? null, $learner);
            if ($track->isFull()) {
                throw ValidationException::withMessages(['user_id' => 'This track has reached its participant limit.']);
            }
            if ($track->enrollments()->where('user_id', $learner->id)->exists()) {
                throw ValidationException::withMessages(['user_id' => 'This learner already has an enrollment in this track.']);
            }
            InternshipEnrollment::create($validated);
        }, 3);

        return back()->with('success', 'Learner enrolled.');
    }

    public function updateEnrollment(Request $request, InternshipEnrollment $enrollment)
    {
        $validated = $request->validate([
            'status' => 'in:enrolled,active,completed,withdrawn,failed',
            'sponsor_id' => 'nullable|exists:users,id',
            'project_title' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($enrollment, $validated) {
            $learner = User::whereKey($enrollment->user_id)->lockForUpdate()->firstOrFail();
            $enrollment = InternshipEnrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
            if (array_key_exists('sponsor_id', $validated)) {
                $this->validateSponsor($validated['sponsor_id'], $learner);
            }
            $status = $validated['status'] ?? $enrollment->status;
            if ($enrollment->status === 'completed' && $status !== 'completed') {
                throw ValidationException::withMessages(['status' => 'Earned completion is retained. Manage certificate revocation separately.']);
            }
            if ($status === 'completed' && $enrollment->status !== 'completed') {
                app(CertificateService::class)->checkInternshipCompletion($enrollment);
                if ($enrollment->fresh()->status !== 'completed') {
                    throw ValidationException::withMessages(['status' => 'Completion requires approved submissions for every task and milestone.']);
                }
            }
            $enrollment->refresh()->update($validated);
        }, 3);

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
        abort_if($enrollment->status === 'completed', 409, 'Completed internship records are retained.');
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:daily,weekly,monthly',
            'status' => 'required|in:backlog,this_week,today,in_progress,in_review,approved,needs_revision,done',
            'priority' => 'required|in:low,medium,high,urgent',
            'milestone_id' => ['nullable', Rule::exists('internship_milestones', 'id')->where('track_id', $enrollment->track_id)],
            'due_date' => 'nullable|date',
        ]);

        $validated['enrollment_id'] = $enrollment->id;
        $validated['assigned_to'] = $enrollment->user_id;
        $validated['sort_order'] = InternshipTask::where('enrollment_id', $enrollment->id)->count();

        $this->mutateTasks($enrollment, fn () => InternshipTask::create($validated));

        return back()->with('success', 'Task added to intern\'s board.');
    }

    public function updateTask(Request $request, InternshipTask $task)
    {
        abort_if($task->submissions()->exists() || $task->enrollment->status === 'completed', 409, 'Submitted tasks are retained with their review history.');
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|in:daily,weekly,monthly',
            'status' => 'sometimes|in:backlog,this_week,today,in_progress,in_review,approved,needs_revision,done',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'milestone_id' => ['nullable', Rule::exists('internship_milestones', 'id')->where('track_id', $task->enrollment->track_id)],
            'due_date' => 'nullable|date',
        ]);

        $this->mutateTasks($task->enrollment, function () use ($task, $validated) {
            $task = InternshipTask::whereKey($task->id)->lockForUpdate()->firstOrFail();
            abort_if($task->submissions()->exists(), 409, 'Submitted tasks are retained with their review history.');
            $task->update($validated);
        });

        return back()->with('success', 'Task updated.');
    }

    public function destroyTask(InternshipTask $task)
    {
        abort_if($task->submissions()->exists() || $task->enrollment->status === 'completed', 409, 'Submitted tasks and earned results must be retained.');
        $this->mutateTasks($task->enrollment, function () use ($task) {
            $task = InternshipTask::whereKey($task->id)->lockForUpdate()->firstOrFail();
            abort_if($task->submissions()->exists(), 409, 'Submitted tasks and earned results must be retained.');
            $task->delete();
        });

        return back()->with('success', 'Task deleted.');
    }

    private function mutateTasks(InternshipEnrollment $enrollment, callable $mutation): void
    {
        DB::transaction(function () use ($enrollment, $mutation) {
            User::whereKey($enrollment->user_id)->lockForUpdate()->firstOrFail();
            $current = InternshipEnrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
            abort_if($current->status === 'completed', 409, 'Completed internship records are retained.');
            $mutation();
        }, 3);
    }

    private function validateSponsor(?int $sponsorId, User $learner): void
    {
        if ($sponsorId === null) {
            return;
        }
        $sponsor = User::whereKey($sponsorId)->where('is_active', true)->where('organization_id', $learner->organization_id)->first();
        if (! $sponsor || $sponsor->id === $learner->id || ! $sponsor->hasRole('sponsor')) {
            throw ValidationException::withMessages(['sponsor_id' => "Choose an active sponsor in the learner's organization who is not the learner."]);
        }
    }
}
