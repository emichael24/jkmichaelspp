<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\User;
use Illuminate\Support\Str;

class CertificateService
{
    /**
     * Issue a certificate for a course or internship completion.
     */
    public function issue(User $user, $certifiable, array $metadata = []): Certificate
    {
        $uuid = (string) Str::uuid();
        $title = $this->resolveTitle($certifiable);

        return Certificate::create([
            'uuid'               => $uuid,
            'user_id'            => $user->id,
            'certifiable_type'   => get_class($certifiable),
            'certifiable_id'     => $certifiable->id,
            'title'              => $title,
            'certificate_number' => $this->generateNumber(),
            'issued_at'          => now(),
            'pdf_url'            => $this->generatePdfUrl($uuid),
            'qr_code_url'        => $this->generateQrCodeUrl($uuid),
            'metadata'           => $metadata,
        ]);
    }

    /**
     * Detect internship completion and auto-issue certificate.
     */
    public function checkInternshipCompletion($enrollment): ?Certificate
    {
        if ($enrollment->status !== 'active') {
            return null;
        }

        $totalTasks = $enrollment->tasks()->count();
        $doneTasks = $enrollment->tasks()->whereIn('status', ['done', 'approved'])->count();

        if ($totalTasks === 0 || $doneTasks < $totalTasks) {
            return null;
        }

        $existing = Certificate::where('certifiable_type', get_class($enrollment->track))
            ->where('certifiable_id', $enrollment->track->id)
            ->where('user_id', $enrollment->user_id)
            ->first();

        if ($existing) {
            return null;
        }

        $enrollment->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        return $this->issue($enrollment->user, $enrollment->track, [
            'score'    => $enrollment->overall_score,
            'duration' => $enrollment->track->duration_weeks . ' weeks',
            'skills'   => $enrollment->track->skills_covered ?? [],
        ]);
    }

    /**
     * Generate a unique certificate number.
     */
    private function generateNumber(): string
    {
        do {
            $number = 'JKM-' . date('Y') . '-' . strtoupper(Str::random(5));
        } while (Certificate::where('certificate_number', $number)->exists());

        return $number;
    }

    /**
     * Resolve the certificate title from the certifiable model.
     */
    private function resolveTitle($certifiable): string
    {
        if (method_exists($certifiable, 'getTitle')) {
            return 'Certificate of Completion - ' . $certifiable->getTitle();
        }

        return 'Certificate of Completion - ' . ($certifiable->title ?? $certifiable->name ?? 'Program');
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
