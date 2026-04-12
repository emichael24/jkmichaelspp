<?php

namespace App\Http\Controllers;

use App\Models\InternshipEnrollment;
use App\Models\InternshipSubmission;
use App\Models\InternshipEvidence;
use App\Models\InternshipTask;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InternshipSubmissionController extends Controller
{
    /**
     * Submit work for a task.
     */
    public function store(Request $request, InternshipTask $task)
    {
        $enrollment = $task->enrollment;
        $this->authorizeEnrollment($enrollment);

        $validated = $request->validate([
            'content'          => 'nullable|string|max:10000',
            'file_urls'        => 'nullable|array',
            'file_urls.*'      => 'string|max:500',
            'time_log_minutes' => 'nullable|integer|min:0',
            'evidence'         => 'nullable|array',
            'evidence.*.type'  => 'required_with:evidence|in:screenshot,photo,screen_recording,document,other',
            'evidence.*.file_url'       => 'required_with:evidence|string|max:500',
            'evidence.*.caption'        => 'nullable|string|max:500',
            'evidence.*.file_type'      => 'nullable|string|max:10',
            'evidence.*.file_size_bytes' => 'nullable|integer|min:0',
        ]);

        $submissionNumber = InternshipSubmission::where('task_id', $task->id)
            ->where('user_id', $request->user()->id)
            ->max('submission_number') + 1;

        $submission = InternshipSubmission::create([
            'task_id'           => $task->id,
            'user_id'           => $request->user()->id,
            'submission_number' => $submissionNumber,
            'content'           => $validated['content'] ?? null,
            'file_urls'         => $validated['file_urls'] ?? null,
            'time_log_minutes'  => $validated['time_log_minutes'] ?? null,
            'status'            => 'submitted',
            'submitted_at'      => now(),
        ]);

        // Store evidence
        if (!empty($validated['evidence'])) {
            foreach ($validated['evidence'] as $ev) {
                InternshipEvidence::create([
                    'submission_id'  => $submission->id,
                    'type'           => $ev['type'],
                    'file_url'       => $ev['file_url'],
                    'caption'        => $ev['caption'] ?? null,
                    'file_type'      => $ev['file_type'] ?? null,
                    'file_size_bytes' => $ev['file_size_bytes'] ?? null,
                ]);
            }
        }

        // Move task to in_review
        $task->update(['status' => 'in_review']);

        return back()->with('success', 'Submission created successfully.');
    }

    /**
     * List learner's submissions.
     */
    public function index(Request $request)
    {
        $enrollment = InternshipEnrollment::where('user_id', $request->user()->id)
            ->whereIn('status', ['enrolled', 'active'])
            ->latest()
            ->firstOrFail();

        $submissions = InternshipSubmission::where('user_id', $request->user()->id)
            ->whereHas('task', fn ($q) => $q->where('enrollment_id', $enrollment->id))
            ->with(['task', 'evidence', 'latestReview.sponsor'])
            ->latest('submitted_at')
            ->paginate(15);

        return Inertia::render('Internship/Submissions', [
            'enrollment'  => $enrollment->load('track'),
            'submissions' => $submissions,
        ]);
    }

    /**
     * Show submission detail.
     */
    public function show(InternshipSubmission $submission)
    {
        $this->authorizeSubmission($submission);

        $submission->load(['task.milestone', 'evidence', 'reviews.sponsor']);

        return Inertia::render('Internship/SubmissionDetail', [
            'submission' => $submission,
        ]);
    }

    /**
     * Resubmit after revision request.
     */
    public function resubmit(Request $request, InternshipSubmission $submission)
    {
        $this->authorizeSubmission($submission);

        if ($submission->status !== 'revision_requested') {
            return back()->with('error', 'Only revision-requested submissions can be resubmitted.');
        }

        $validated = $request->validate([
            'content'          => 'nullable|string|max:10000',
            'file_urls'        => 'nullable|array',
            'file_urls.*'      => 'string|max:500',
            'time_log_minutes' => 'nullable|integer|min:0',
        ]);

        $newSubmission = InternshipSubmission::create([
            'task_id'           => $submission->task_id,
            'user_id'           => $request->user()->id,
            'submission_number' => $submission->submission_number + 1,
            'content'           => $validated['content'] ?? null,
            'file_urls'         => $validated['file_urls'] ?? null,
            'time_log_minutes'  => $validated['time_log_minutes'] ?? null,
            'status'            => 'submitted',
            'submitted_at'      => now(),
        ]);

        // Move task back to in_review
        $submission->task->update(['status' => 'in_review']);

        return back()->with('success', 'Resubmission successful.');
    }

    /* ── Authorization ─────────────────────────────── */

    private function authorizeEnrollment(InternshipEnrollment $enrollment): void
    {
        $user = auth()->user();
        if ($enrollment->user_id !== $user->id && !$user->hasRole('platform_admin')) {
            abort(403);
        }
    }

    private function authorizeSubmission(InternshipSubmission $submission): void
    {
        $user = auth()->user();
        if ($submission->user_id !== $user->id && !$user->hasRole('platform_admin')) {
            abort(403);
        }
    }
}
