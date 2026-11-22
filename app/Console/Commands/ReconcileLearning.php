<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Services\CourseCompletionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ReconcileLearning extends Command
{
    protected $signature = 'learning:reconcile {--apply : Reconcile eligible active enrollments; never erase historical completion}';

    protected $description = 'Compare completion claims with published lesson and assessment evidence; save a private review report';

    public function handle(CourseCompletionService $completion): int
    {
        $report = ['checked_at' => now()->toIso8601String(), 'applied' => (bool) $this->option('apply'), 'checked' => 0,
            'evidence_supported' => 0, 'reconciled' => 0, 'historical_review_required' => [], 'unavailable_accounts' => 0];
        foreach (CourseEnrollment::with(['user.organization', 'course'])->lazyById(100) as $enrollment) {
            $report['checked']++;
            if (! $enrollment->course || ! $enrollment->user) {
                $report['historical_review_required'][] = ['enrollment_id' => $enrollment->id, 'reason' => 'Related course or account is archived.'];

                continue;
            }
            $evidence = $completion->evidence($enrollment->user, $enrollment->course);
            if ($evidence['eligible']) {
                $report['evidence_supported']++;
            } elseif ($enrollment->status === 'completed') {
                $report['historical_review_required'][] = ['enrollment_id' => $enrollment->id, 'course_id' => $enrollment->course_id,
                    'reason' => 'Historical completion is not supported by the currently published curriculum. Preserve the original until its historical evidence is reviewed.',
                    ...$evidence];
            }
            $accountActive = $enrollment->user->is_active && (! $enrollment->user->organization_id || $enrollment->user->organization?->is_active);
            if (! $accountActive) {
                $report['unavailable_accounts']++;

                continue;
            }
            if ($this->option('apply') && $evidence['eligible'] && $enrollment->allowsAccess()) {
                $completion->reconcile($enrollment->user, $enrollment->course);
                $report['reconciled']++;
            }
        }
        $report['course_certificates'] = Certificate::where('certifiable_type', Course::class)->count();
        $path = 'operations/learning-reconciliation-'.now()->format('Ymd-His').'.json';
        Storage::disk('local')->put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $summary = collect($report)->except('historical_review_required')->all();
        $summary['historical_review_required'] = count($report['historical_review_required']);
        $summary['private_report'] = $path;
        AuditLog::log('learning_reconciliation', CourseEnrollment::class, 0, null, $summary);
        $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $summary['historical_review_required'] ? self::FAILURE : self::SUCCESS;
    }
}
