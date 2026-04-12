<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    protected $fillable = [
        'author_id',
        'cohort_id',
        'organization_id',
        'title',
        'body',
        'is_pinned',
        'published_at',
        'expires_at',
    ];

    protected $casts = [
        'is_pinned'    => 'boolean',
        'published_at' => 'datetime',
        'expires_at'   => 'datetime',
    ];

    /* ── relationships ── */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /* ── scopes ── */
    public function scopePublished($q)
    {
        return $q->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function scopeActive($q)
    {
        return $q->published()
                 ->where(function ($q) {
                     $q->whereNull('expires_at')
                       ->orWhere('expires_at', '>', now());
                 });
    }

    public function scopePinned($q)
    {
        return $q->where('is_pinned', true);
    }

    public function scopeForCohort($q, int $cohortId)
    {
        return $q->where('cohort_id', $cohortId);
    }
}
