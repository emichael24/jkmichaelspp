<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortfolioItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'skills_demonstrated' => 'array',
        'is_public'           => 'boolean',
    ];

    /* ── Relationships ─────────────────────────────── */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(InternshipEnrollment::class, 'enrollment_id');
    }

    /* ── Scopes ────────────────────────────────────── */

    public function scopePublic($q)
    {
        return $q->where('is_public', true);
    }

    public function scopeForUser($q, int $userId)
    {
        return $q->where('user_id', $userId);
    }

    public function scopeOrdered($q)
    {
        return $q->orderBy('sort_order');
    }
}
