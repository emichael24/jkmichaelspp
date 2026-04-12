<?php

namespace App\Http\Controllers;

use App\Models\InternshipEnrollment;
use App\Models\InternshipSubmission;
use App\Models\SponsorReview;
use App\Models\SponsorScorecard;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SponsorController extends Controller
{
    /**
     * My Interns — list all interns assigned to this sponsor.
     */
    public function myInterns(Request $request)
    {
        $sponsorId = $request->user()->id;

        $enrollments = InternshipEnrollment::where('sponsor_id', $sponsorId)
            ->with(['user', 'track'])
            ->withCount([
                'tasks',
                'tasks as completed_tasks_count' => fn ($q) => $q->whereIn('status', ['done', 'approved']),
            ])
            ->latest('start_date')
            ->paginate(20);

        // Count pending submissions per enrollment
        $enrollmentIds = $enrollments->pluck('id');
        $pendingCounts = \App\Models\InternshipSubmission::whereHas('task', function ($q) use ($enrollmentIds) {
            $q->whereIn('enrollment_id', $enrollmentIds);
        })
            ->whereIn('status', ['submitted', 'in_review'])
            ->get()
            ->groupBy(fn ($s) => $s->task->enrollment_id)
            ->map->count();

        $enrollments->getCollection()->transform(function ($enr) use ($pendingCounts) {
            $enr->pending_submissions_count = $pendingCounts[$enr->id] ?? 0;
            return $enr;
        });

        return Inertia::render('Sponsor/Interns', [
            'enrollments' => $enrollments,
        ]);
    }

    /**
     * Scorecards — list all scorecards submitted by this sponsor.
     */
    public function myScorecards(Request $request)
    {
        $sponsorId = $request->user()->id;

        $scorecards = SponsorScorecard::where('sponsor_id', $sponsorId)
            ->with(['enrollment.user', 'enrollment.track'])
            ->latest()
            ->paginate(20);

        return Inertia::render('Sponsor/Scorecards', [
            'scorecards' => $scorecards,
        ]);
    }

    /**
     * Review queue — submissions pending review from sponsor's interns.
     */
    public function reviewQueue(Request $request)
    {
        $sponsorId = $request->user()->id;

        $submissions = InternshipSubmission::whereHas('task.enrollment', function ($q) use ($sponsorId) {
            $q->where('sponsor_id', $sponsorId);
        })
            ->whereIn('status', ['submitted', 'in_review'])
            ->with(['task.enrollment.user', 'task.enrollment.track', 'evidence'])
            ->latest('submitted_at')
            ->paginate(20);

        return Inertia::render('Sponsor/ReviewQueue', [
            'submissions' => $submissions,
        ]);
    }

    /**
     * Show submission detail for sponsor review.
     */
    public function showSubmission(InternshipSubmission $submission)
    {
        $this->authorizeSponsor($submission);

        $submission->load(['task.milestone', 'user', 'evidence', 'reviews.sponsor']);

        return Inertia::render('Sponsor/ReviewSubmission', [
            'submission' => $submission,
        ]);
    }

    /**
     * Submit a review for a submission.
     */
    public function review(Request $request, InternshipSubmission $submission)
    {
        $this->authorizeSponsor($submission);

        $validated = $request->validate([
            'decision'            => 'required|in:approved,revision_requested',
            'rubric_scores'       => 'nullable|array',
            'overall_score'       => 'nullable|numeric|min:0|max:5',
            'feedback'            => 'nullable|string|max:5000',
            'feedback_audio_url'  => 'nullable|string|max:500',
            'risk_flags'          => 'nullable|array',
            'risk_flags.*'        => 'string|max:50',
        ]);

        SponsorReview::create([
            'submission_id'      => $submission->id,
            'sponsor_id'         => $request->user()->id,
            'decision'           => $validated['decision'],
            'rubric_scores'      => $validated['rubric_scores'] ?? null,
            'overall_score'      => $validated['overall_score'] ?? null,
            'feedback'           => $validated['feedback'] ?? null,
            'feedback_audio_url' => $validated['feedback_audio_url'] ?? null,
            'risk_flags'         => $validated['risk_flags'] ?? null,
            'reviewed_at'        => now(),
        ]);

        // Update submission status
        $submission->update(['status' => $validated['decision']]);

        // If approved, move task to done
        if ($validated['decision'] === 'approved') {
            $submission->task->update(['status' => 'done']);
        } else {
            $submission->task->update(['status' => 'needs_revision']);
        }

        return back()->with('success', 'Review submitted.');
    }

    /**
     * View intern progress.
     */
    public function internProgress(Request $request, InternshipEnrollment $enrollment)
    {
        $this->authorizeSponsorForEnrollment($enrollment, $request->user());

        $enrollment->load([
            'user',
            'track.milestones',
            'tasks',
            'tasks.submissions.latestReview',
        ]);

        $scorecards = SponsorScorecard::forEnrollment($enrollment->id)
            ->orderBy('period_number')
            ->get();

        return Inertia::render('Sponsor/InternProgress', [
            'enrollment' => $enrollment,
            'scorecards' => $scorecards,
        ]);
    }

    /**
     * Submit a scorecard.
     */
    public function storeScorecard(Request $request)
    {
        $validated = $request->validate([
            'enrollment_id'       => 'required|exists:internship_enrollments,id',
            'period_type'         => 'required|in:weekly,monthly',
            'period_number'       => 'required|integer|min:1',
            'scores'              => 'required|array',
            'overall_score'       => 'required|numeric|min:0|max:5',
            'comments'            => 'nullable|string|max:5000',
            'improvement_actions' => 'nullable|array',
            'improvement_actions.*' => 'string|max:255',
            'ready_for_next'      => 'boolean',
        ]);

        $enrollment = InternshipEnrollment::findOrFail($validated['enrollment_id']);
        $this->authorizeSponsorForEnrollment($enrollment, $request->user());

        $scorecard = SponsorScorecard::create([
            'enrollment_id'       => $enrollment->id,
            'sponsor_id'          => $request->user()->id,
            'period_type'         => $validated['period_type'],
            'period_number'       => $validated['period_number'],
            'scores'              => $validated['scores'],
            'overall_score'       => $validated['overall_score'],
            'comments'            => $validated['comments'] ?? null,
            'improvement_actions' => $validated['improvement_actions'] ?? null,
            'ready_for_next'      => $validated['ready_for_next'] ?? false,
        ]);

        // Update enrollment overall score (rolling average)
        $avgScore = SponsorScorecard::forEnrollment($enrollment->id)->avg('overall_score');
        $enrollment->update(['overall_score' => round($avgScore, 2)]);

        return back()->with('success', 'Scorecard submitted.');
    }

    /**
     * Scorecard form.
     */
    public function scorecardForm(Request $request, InternshipEnrollment $enrollment)
    {
        $this->authorizeSponsorForEnrollment($enrollment, $request->user());

        $enrollment->load(['user', 'track']);

        $existingScorecards = SponsorScorecard::forEnrollment($enrollment->id)
            ->orderBy('period_number')
            ->get();

        return Inertia::render('Sponsor/Scorecard', [
            'enrollment' => $enrollment,
            'existingScorecards' => $existingScorecards,
        ]);
    }

    /* ── Authorization ─────────────────────────────── */

    private function authorizeSponsor(InternshipSubmission $submission): void
    {
        $user = auth()->user();
        $enrollment = $submission->task->enrollment;

        if ($enrollment->sponsor_id !== $user->id && !$user->hasRole('platform_admin')) {
            abort(403);
        }
    }

    private function authorizeSponsorForEnrollment(InternshipEnrollment $enrollment, $user): void
    {
        if ($enrollment->sponsor_id !== $user->id && !$user->hasRole('platform_admin')) {
            abort(403);
        }
    }
}
