<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\InternshipEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CertificateService
{
    /**
     * Issue a certificate for a course or internship completion.
     */
    public function issue(User $user, $certifiable, array $metadata = []): Certificate
    {
        return DB::transaction(function () use ($user, $certifiable, $metadata) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = Certificate::where('user_id', $user->id)->where('certifiable_type', get_class($certifiable))->where('certifiable_id', $certifiable->id)->first();
            if ($existing) {
                return $existing; // Revocation is final; retries never create a replacement credential.
            }
            $uuid = (string) Str::uuid();
            $title = $this->resolveTitle($certifiable);

            return Certificate::create([
                'uuid' => $uuid,
                'user_id' => $user->id,
                'certifiable_type' => get_class($certifiable),
                'certifiable_id' => $certifiable->id,
                'title' => $title,
                'certificate_number' => $this->generateNumber(),
                'issued_at' => now(),
                'pdf_url' => $this->generatePdfUrl($uuid),
                'qr_code_url' => $this->generateQrCodeUrl($uuid),
                'metadata' => $metadata,
            ]);
        }, 3);
    }

    /**
     * Detect internship completion and auto-issue certificate.
     */
    public function checkInternshipCompletion($enrollment): ?Certificate
    {
        return DB::transaction(function () use ($enrollment) {
            User::whereKey($enrollment->user_id)->lockForUpdate()->firstOrFail();
            $enrollment = InternshipEnrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
            if ($enrollment->status !== 'active') {
                return null;
            }
            $tasks = $enrollment->tasks()->orderBy('id')->lockForUpdate()->get();
            if ($tasks->isEmpty()) {
                return null;
            }
            $milestones = $enrollment->track->milestones()->pluck('id');
            if ($milestones->diff($tasks->pluck('milestone_id')->filter())->isNotEmpty()) {
                return null;
            }

            $reviews = collect();
            foreach ($tasks as $task) {
                $submission = $task->submissions()->orderByDesc('submission_number')->orderByDesc('id')->first();
                $review = $submission?->latestReview;
                if (! in_array($task->status, ['approved', 'done'], true) || ! $submission || $submission->user_id !== $enrollment->user_id
                    || $submission->status !== 'approved' || ! $review || $review->decision !== 'approved'
                    || $review->sponsor_id === $enrollment->user_id || ! $review->rubric_scores || $review->overall_score === null) {
                    return null;
                }
                $reviews->push($review);
            }
            $enrollment->update(['status' => 'completed', 'completed_at' => $enrollment->completed_at ?? now(),
                'overall_score' => round($reviews->avg('overall_score'), 2)]);

            return $this->issue($enrollment->user, $enrollment->track, [
                'score' => $enrollment->overall_score, 'duration' => $enrollment->track->duration_weeks.' weeks',
                'skills' => $enrollment->track->skills_covered ?? [], 'task_ids' => $tasks->modelKeys(),
                'review_ids' => $reviews->pluck('id')->all(), 'milestone_ids' => $milestones->all(),
                'completion_policy' => 'reviewed_internship_milestones_v1',
            ]);
        }, 3);
    }

    /**
     * Generate a unique certificate number.
     */
    private function generateNumber(): string
    {
        do {
            $number = 'JKM-'.date('Y').'-'.strtoupper(Str::random(5));
        } while (Certificate::where('certificate_number', $number)->exists());

        return $number;
    }

    /**
     * Resolve the certificate title from the certifiable model.
     */
    private function resolveTitle($certifiable): string
    {
        if (method_exists($certifiable, 'getTitle')) {
            return 'Certificate of Completion - '.$certifiable->getTitle();
        }

        return 'Certificate of Completion - '.($certifiable->title ?? $certifiable->name ?? 'Program');
    }

    private function generatePdfUrl(string $uuid): string
    {
        return url("/certificates/{$uuid}/download");
    }

    private function generateQrCodeUrl(string $uuid): string
    {
        return route('certificates.verify', $uuid);
    }
}
