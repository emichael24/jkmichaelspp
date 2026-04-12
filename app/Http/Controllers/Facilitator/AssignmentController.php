<?php

namespace App\Http\Controllers\Facilitator;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Cohort;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AssignmentController extends Controller
{
    /* ── List assignments for a cohort ──────────────── */

    public function index(Cohort $cohort)
    {
        $this->authorizeFacilitator($cohort);

        $assignments = Assignment::where('cohort_id', $cohort->id)
            ->withCount('submissions')
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Facilitator/Assignments/Index', [
            'cohort'      => $cohort,
            'assignments' => $assignments,
        ]);
    }

    /* ── Create form ───────────────────────────────── */

    public function create(Cohort $cohort)
    {
        $this->authorizeFacilitator($cohort);

        return Inertia::render('Facilitator/Assignments/Create', [
            'cohort' => $cohort->load('course'),
        ]);
    }

    /* ── Store ─────────────────────────────────────── */

    public function store(Request $request, Cohort $cohort)
    {
        $this->authorizeFacilitator($cohort);

        $validated = $request->validate([
            'title'              => 'required|string|max:255',
            'description'        => 'required|string',
            'instructions'       => 'nullable|string',
            'rubric'             => 'nullable|array',
            'rubric.*.criterion' => 'required_with:rubric|string',
            'rubric.*.description' => 'nullable|string',
            'rubric.*.max_score' => 'required_with:rubric|integer|min:1',
            'max_score'          => 'integer|min:1|max:1000',
            'due_date'           => 'nullable|date',
            'allow_late'         => 'boolean',
            'max_resubmissions'  => 'integer|min:0|max:10',
        ]);

        $validated['course_id']  = $cohort->course_id;
        $validated['cohort_id']  = $cohort->id;
        $validated['created_by'] = Auth::id();

        Assignment::create($validated);

        return redirect()->route('facilitator.assignments.index', $cohort)
            ->with('success', 'Assignment created.');
    }

    /* ── Submissions / grading queue ───────────────── */

    public function submissions(Cohort $cohort, Assignment $assignment)
    {
        $this->authorizeFacilitator($cohort);

        $submissions = $assignment->submissions()
            ->with('user')
            ->orderByRaw("FIELD(status, 'submitted','resubmitted','in_review','returned','graded')")
            ->orderByDesc('submitted_at')
            ->get();

        return Inertia::render('Facilitator/Assignments/Grading', [
            'cohort'      => $cohort,
            'assignment'  => $assignment,
            'submissions' => $submissions,
        ]);
    }

    /* ── Grade a submission ────────────────────────── */

    public function grade(Request $request, Cohort $cohort, AssignmentSubmission $submission)
    {
        $this->authorizeFacilitator($cohort);

        $validated = $request->validate([
            'score'         => 'required|integer|min:0',
            'rubric_scores' => 'nullable|array',
            'feedback'      => 'nullable|string',
            'status'        => 'required|in:graded,returned',
        ]);

        $submission->update([
            'score'         => $validated['score'],
            'rubric_scores' => $validated['rubric_scores'] ?? null,
            'feedback'      => $validated['feedback'] ?? null,
            'status'        => $validated['status'],
            'graded_by'     => Auth::id(),
            'graded_at'     => now(),
        ]);

        return back()->with('success', 'Submission graded.');
    }

    /* ── Edit assignment ───────────────────────────── */

    public function edit(Cohort $cohort, Assignment $assignment)
    {
        $this->authorizeFacilitator($cohort);

        return Inertia::render('Facilitator/Assignments/Edit', [
            'cohort'     => $cohort,
            'assignment' => $assignment,
        ]);
    }

    /* ── Update assignment ─────────────────────────── */

    public function update(Request $request, Cohort $cohort, Assignment $assignment)
    {
        $this->authorizeFacilitator($cohort);

        $validated = $request->validate([
            'title'              => 'required|string|max:255',
            'description'        => 'required|string',
            'instructions'       => 'nullable|string',
            'rubric'             => 'nullable|array',
            'max_score'          => 'integer|min:1|max:1000',
            'due_date'           => 'nullable|date',
            'allow_late'         => 'boolean',
            'max_resubmissions'  => 'integer|min:0|max:10',
            'is_published'       => 'boolean',
        ]);

        $assignment->update($validated);

        return redirect()->route('facilitator.assignments.index', $cohort)
            ->with('success', 'Assignment updated.');
    }

    /* ── Delete assignment ─────────────────────────── */

    public function destroy(Cohort $cohort, Assignment $assignment)
    {
        $this->authorizeFacilitator($cohort);
        $assignment->delete();

        return back()->with('success', 'Assignment deleted.');
    }

    /* ── Auth helper ───────────────────────────────── */

    private function authorizeFacilitator(Cohort $cohort): void
    {
        $user = Auth::user();
        if ($cohort->facilitator_id !== $user->id && !$user->hasRole('platform_admin')) {
            abort(403, 'Unauthorized');
        }
    }
}
