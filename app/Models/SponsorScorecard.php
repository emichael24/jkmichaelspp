<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SponsorScorecard extends Model
{
    protected $guarded = [];

    protected $casts = [
        'scores'              => 'array',
        'improvement_actions' => 'array',
        'ready_for_next'      => 'boolean',
    ];

    /* ── Relationships ─────────────────────────────── */

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(InternshipEnrollment::class, 'enrollment_id');
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sponsor_id');
    }

    /* ── Scopes ────────────────────────────────────── */

    public function scopeForEnrollment($q, int $enrollmentId)
    {
        return $q->where('enrollment_id', $enrollmentId);
    }

    public function scopeWeekly($q)
    {
        return $q->where('period_type', 'weekly');
    }

    public function scopeMonthly($q)
    {
        return $q->where('period_type', 'monthly');
    }
}
