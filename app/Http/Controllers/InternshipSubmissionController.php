<?php

namespace App\Http\Controllers;

use App\Models\InternshipEnrollment;
use App\Models\InternshipSubmission;
use App\Models\InternshipTask;
use App\Services\InternshipWorkflowService;
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
            'content' => 'nullable|string|max:10000',
            'file_urls' => 'nullable|array|max:5',
            'file_urls.*' => 'url:http,https|max:500',
            'time_log_minutes' => 'nullable|integer|min:0|max:10080',
            'evidence' => 'nullable|array|max:10',
            'evidence.*.type' => 'required_with:evidence|in:screenshot,photo,screen_recording,document,other',
            'evidence.*.file_url' => 'required_with:evidence|url:http,https|max:500',
            'evidence.*.caption' => 'nullable|string|max:500',
            'evidence.*.file_type' => 'nullable|string|max:10',
            'evidence.*.file_size_bytes' => 'nullable|integer|min:0',
        ]);

        app(InternshipWorkflowService::class)->submit($task, $request->user(), $validated);

        return back()->with('success', 'Submission created successfully.');
    }

    /**
     * List learner's submissions.
     */
    public function index(Request $request)
    {
        $enrollment = InternshipEnrollment::where('user_id', $request->user()->id)
            ->whereIn('status', ['enrolled', 'active', 'completed'])
            ->latest()
            ->firstOrFail();

        $submissions = InternshipSubmission::where('user_id', $request->user()->id)
            ->whereHas('task', fn ($q) => $q->where('enrollment_id', $enrollment->id))
            ->with(['task', 'evidence', 'latestReview.sponsor'])
            ->latest('submitted_at')
            ->paginate(15);

        return Inertia::render('Internship/Submissions', [
            'enrollment' => $enrollment->load('track'),
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
            'content' => 'nullable|string|max:10000',
            'file_urls' => 'nullable|array|max:5',
            'file_urls.*' => 'url:http,https|max:500',
            'time_log_minutes' => 'nullable|integer|min:0|max:10080',
        ]);

        app(InternshipWorkflowService::class)->submit($submission->task, $request->user(), $validated, $submission->id);

        return back()->with('success', 'Resubmission successful.');
    }

    /* ── Authorization ─────────────────────────────── */

    private function authorizeEnrollment(InternshipEnrollment $enrollment): void
    {
        $user = auth()->user();
        if ($enrollment->user_id !== $user->id && ! $user->hasRole('platform_admin')) {
            abort(403);
        }
    }

    private function authorizeSubmission(InternshipSubmission $submission): void
    {
        $user = auth()->user();
        if ($submission->user_id !== $user->id && ! $user->hasRole('platform_admin')) {
            abort(403);
        }
    }
}
