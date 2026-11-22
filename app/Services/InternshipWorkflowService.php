<?php

namespace App\Services;

use App\Models\InternshipEnrollment;
use App\Models\InternshipSubmission;
use App\Models\InternshipTask;
use App\Models\SponsorReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InternshipWorkflowService
{
    public function authorizeSponsor(InternshipEnrollment $enrollment, User $actor): void
    {
        abort_unless($actor->hasRole('platform_admin') || ($enrollment->sponsor_id === $actor->id
            && $enrollment->user?->organization_id === $actor->organization_id), 403);
        abort_if($enrollment->user_id === $actor->id, 403, 'A learner cannot review their own work.');
    }

    // Every submission, review and completion follows the same lock order.
    private function lockedEnrollment(InternshipTask $task): InternshipEnrollment
    {
        $enrollment = $task->enrollment;
        User::whereKey($enrollment->user_id)->lockForUpdate()->firstOrFail();

        return InternshipEnrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
    }

    public function submit(InternshipTask $task, User $actor, array $data, ?int $previous = null): InternshipSubmission
    {
        return DB::transaction(function () use ($task, $actor, $data, $previous) {
            $enrollment = $this->lockedEnrollment($task);
            abort_unless($enrollment->user_id === $actor->id, 403);
            abort_unless(in_array($enrollment->status, ['enrolled', 'active'], true), 409, 'This internship is no longer accepting submissions.');
            $task = InternshipTask::whereKey($task->id)->lockForUpdate()->firstOrFail();
            $latest = InternshipSubmission::where('task_id', $task->id)->orderByDesc('submission_number')->orderByDesc('id')->lockForUpdate()->first();
            abort_if($latest && ($latest->status !== 'revision_requested' || ($previous !== null && $latest->id !== $previous)), 409, 'This task already has a submission awaiting review or approval. Refresh to see its latest version.');
            abort_if($previous !== null && ! $latest, 409);
            if (trim($data['content'] ?? '') === '' && empty($data['file_urls']) && empty($data['evidence'])) {
                throw ValidationException::withMessages(['content' => 'Add your work, a file link or supporting evidence before submitting.']);
            }
            $submission = InternshipSubmission::create([
                'task_id' => $task->id, 'user_id' => $enrollment->user_id,
                'submission_number' => ($latest?->submission_number ?? 0) + 1,
                'content' => $data['content'] ?? null, 'file_urls' => $data['file_urls'] ?? null,
                'time_log_minutes' => $data['time_log_minutes'] ?? null,
                'status' => 'submitted', 'submitted_at' => now(),
            ]);
            foreach ($data['evidence'] ?? [] as $evidence) {
                $submission->evidence()->create($evidence);
            }
            $task->update(['status' => 'in_review']);
            if ($enrollment->status === 'enrolled') {
                $enrollment->update(['status' => 'active']);
            }

            return $submission;
        }, 3);
    }

    public function review(InternshipSubmission $submission, User $actor, array $data): void
    {
        DB::transaction(function () use ($submission, $actor, $data) {
            $enrollment = $this->lockedEnrollment($submission->task);
            $this->authorizeSponsor($enrollment, $actor);
            abort_unless(in_array($enrollment->status, ['enrolled', 'active'], true), 409, 'This internship is closed.');
            $task = InternshipTask::whereKey($submission->task_id)->lockForUpdate()->firstOrFail();
            $latest = InternshipSubmission::where('task_id', $task->id)->orderByDesc('submission_number')->orderByDesc('id')->lockForUpdate()->firstOrFail();
            abort_unless($latest->id === $submission->id && $latest->user_id === $enrollment->user_id
                && in_array($latest->status, ['submitted', 'in_review'], true), 409, 'This submission has already been reviewed or superseded. Refresh the review queue.');

            $criteria = $enrollment->track->reviewRubric();
            $scores = $data['rubric_scores'] ?? [];
            if (array_diff(array_keys($scores), $criteria) || ($data['decision'] === 'approved' && array_diff($criteria, array_keys($scores)))) {
                throw ValidationException::withMessages(['rubric_scores' => 'Score every listed criterion before approving. Use only the criteria shown on this review.']);
            }
            $overall = count($scores) ? round(collect($scores)->avg('score'), 1) : null;
            SponsorReview::create([
                'submission_id' => $latest->id, 'sponsor_id' => $actor->id,
                'decision' => $data['decision'], 'rubric_scores' => $scores ?: null,
                'overall_score' => $overall, 'feedback' => $data['feedback'] ?? null,
                'feedback_audio_url' => $data['feedback_audio_url'] ?? null,
                'risk_flags' => $data['risk_flags'] ?? null, 'reviewed_at' => now(),
            ]);
            $latest->update(['status' => $data['decision']]);
            $task->update(['status' => $data['decision'] === 'approved' ? 'done' : 'needs_revision']);
            if ($data['decision'] === 'approved') {
                app(CertificateService::class)->checkInternshipCompletion($enrollment);
            }
        }, 3);
    }
}
