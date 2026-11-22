<?php

namespace App\Models\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;

trait RecordsDomainAudit
{
    public function save(array $options = [])
    {
        return DB::transaction(function () use ($options) {
            $creating = ! $this->exists;
            $old = $this->getRawOriginal();
            $changed = $this->getDirty();
            $saved = parent::save($options);
            if ($saved && ($creating || count($changed))) {
                AuditLog::log($creating ? 'create' : 'update', static::class, (int) $this->getKey(),
                    $creating ? null : $this->auditValues(array_intersect_key($old, $changed)),
                    $this->auditValues($creating ? $this->getAttributes() : $changed));
            }

            return $saved;
        }, 3);
    }

    public function delete()
    {
        return DB::transaction(function () {
            $old = $this->auditValues($this->getAttributes());
            $id = (int) $this->getKey();
            $deleted = parent::delete();
            if ($deleted) {
                AuditLog::log('delete', static::class, $id, $old);
            }

            return $deleted;
        }, 3);
    }

    private function auditValues(array $values): array
    {
        // Explicit fields avoid storing content bodies, answers, tokens, credentials or personal messages.
        return array_intersect_key($values, array_flip([
            'id', 'organization_id', 'user_id', 'course_id', 'module_id', 'lesson_id', 'author_id', 'plan_id', 'status', 'is_active',
            'is_published', 'published_at', 'approval_status', 'approved_by', 'approved_at', 'is_revoked', 'revoked_at',
            'progress_pct', 'completed_at', 'score_pct', 'score', 'grade', 'graded_by', 'graded_at', 'deadline',
            'price', 'discount_price', 'amount_minor', 'currency', 'starts_at', 'ends_at', 'cancels_at', 'canceled_at',
            'key', 'value', 'version_number', 'sort_order', 'marketing_email', 'marketing_whatsapp',
            'track_id', 'enrollment_id', 'task_id', 'submission_id', 'submission_number', 'sponsor_id',
            'decision', 'overall_score', 'reviewed_at', 'period_type', 'period_number',
        ]));
    }
}
