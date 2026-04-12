<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CohortMember extends Model
{
    protected $fillable = [
        'cohort_id',
        'user_id',
        'role',
        'joined_at',
        'status',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
    ];

    /* ── relationships ── */
    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /* ── scopes ── */
    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    public function scopeLearners($q)
    {
        return $q->where('role', 'learner');
    }

    public function scopeCoFacilitators($q)
    {
        return $q->where('role', 'co_facilitator');
    }
}
