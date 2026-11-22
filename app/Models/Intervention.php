<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Intervention extends Model
{
    public const TYPE_NUDGE = 'nudge';

    public const TYPE_COACHING = 'coaching';

    public const TYPE_ESCALATION = 'escalation';

    public const TYPE_PLAN_ADJUSTMENT = 'plan_adjustment';

    public const TYPE_REST_DAY = 'rest_day';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'facilitator_id',
        'learner_id',
        'cohort_id',
        'type',
        'reason',
        'action_taken',
        'status',
    ];

    /* ── relationships ── */
    public function facilitator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'facilitator_id');
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'learner_id');
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    /* ── scopes ── */
    public function scopeForCohort($q, int $cohortId)
    {
        return $q->where('cohort_id', $cohortId);
    }

    public function scopeForLearner($q, int $userId)
    {
        return $q->where('learner_id', $userId);
    }

    public function scopePending($q)
    {
        return $q->where('status', self::STATUS_PENDING);
    }

    public function scopeRecent($q, int $days = 30)
    {
        return $q->where('created_at', '>=', now()->subDays($days));
    }
}
