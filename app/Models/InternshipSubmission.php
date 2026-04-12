<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InternshipSubmission extends Model
{
    protected $guarded = [];

    protected $casts = [
        'file_urls'     => 'array',
        'submitted_at'  => 'datetime',
    ];

    /* ── Relationships ─────────────────────────────── */

    public function task(): BelongsTo
    {
        return $this->belongsTo(InternshipTask::class, 'task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(InternshipEvidence::class, 'submission_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(SponsorReview::class, 'submission_id');
    }

    public function latestReview()
    {
        return $this->hasOne(SponsorReview::class, 'submission_id')->latestOfMany();
    }

    /* ── Scopes ────────────────────────────────────── */

    public function scopePending($q)
    {
        return $q->whereIn('status', ['submitted', 'in_review']);
    }

    public function scopeForUser($q, int $userId)
    {
        return $q->where('user_id', $userId);
    }

    /* ── Helpers ───────────────────────────────────── */

    public function isReviewed(): bool
    {
        return in_array($this->status, ['approved', 'revision_requested']);
    }
}
