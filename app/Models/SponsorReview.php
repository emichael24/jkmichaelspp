<?php

namespace App\Models;

use App\Models\Traits\RecordsDomainAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SponsorReview extends Model
{
    use RecordsDomainAudit;

    protected $guarded = [];

    protected $casts = [
        'rubric_scores' => 'array',
        'risk_flags' => 'array',
        'reviewed_at' => 'datetime',
    ];

    /* ── Relationships ─────────────────────────────── */

    public function submission(): BelongsTo
    {
        return $this->belongsTo(InternshipSubmission::class, 'submission_id');
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sponsor_id');
    }

    /* ── Scopes ────────────────────────────────────── */

    public function scopeApproved($q)
    {
        return $q->where('decision', 'approved');
    }

    public function scopeForSponsor($q, int $sponsorId)
    {
        return $q->where('sponsor_id', $sponsorId);
    }
}
