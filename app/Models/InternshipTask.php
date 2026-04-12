<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternshipTask extends Model
{
    public const STATUS_BACKLOG       = 'backlog';
    public const STATUS_THIS_WEEK     = 'this_week';
    public const STATUS_TODAY          = 'today';
    public const STATUS_IN_PROGRESS   = 'in_progress';
    public const STATUS_IN_REVIEW     = 'in_review';
    public const STATUS_APPROVED      = 'approved';
    public const STATUS_NEEDS_REVISION = 'needs_revision';
    public const STATUS_DONE          = 'done';

    public const KANBAN_COLUMNS = [
        self::STATUS_BACKLOG,
        self::STATUS_THIS_WEEK,
        self::STATUS_TODAY,
        self::STATUS_IN_PROGRESS,
        self::STATUS_IN_REVIEW,
        self::STATUS_NEEDS_REVISION,
        self::STATUS_DONE,
    ];

    protected $fillable = [
        'enrollment_id',
        'milestone_id',
        'title',
        'description',
        'type',
        'status',
        'priority',
        'due_date',
        'assigned_to',
        'sort_order',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    /* ── Relationships ─────────────────────────────── */

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(InternshipEnrollment::class, 'enrollment_id');
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(InternshipMilestone::class, 'milestone_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /* ── Scopes ────────────────────────────────────── */

    public function scopeForEnrollment($query, int $enrollmentId)
    {
        return $query->where('enrollment_id', $enrollmentId);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeDueToday($query)
    {
        return $query->whereDate('due_date', today());
    }

    public function scopeOverdue($query)
    {
        return $query->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->whereNotIn('status', [self::STATUS_APPROVED, self::STATUS_DONE]);
    }

    /* ── Helpers ───────────────────────────────────── */

    public function isOverdue(): bool
    {
        return $this->due_date
            && $this->due_date->isPast()
            && !in_array($this->status, [self::STATUS_APPROVED, self::STATUS_DONE]);
    }
}
