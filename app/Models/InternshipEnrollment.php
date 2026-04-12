<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class InternshipEnrollment extends Model
{
    protected $fillable = [
        'user_id',
        'track_id',
        'sponsor_id',
        'team_lead_id',
        'project_title',
        'start_date',
        'end_date',
        'status',
        'overall_score',
        'completed_at',
    ];

    protected $casts = [
        'start_date'    => 'date',
        'end_date'      => 'date',
        'overall_score' => 'decimal:2',
        'completed_at'  => 'datetime',
    ];

    /* ── Relationships ─────────────────────────────── */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(InternshipTrack::class, 'track_id');
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sponsor_id');
    }

    public function teamLead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_lead_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(InternshipTask::class, 'enrollment_id');
    }

    /* ── Scopes ────────────────────────────────────── */

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['enrolled', 'active']);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /* ── Helpers ───────────────────────────────────── */

    public function progressPct(): float
    {
        $total = $this->tasks()->count();
        if ($total === 0) return 0;

        $done = $this->tasks()->whereIn('status', ['approved', 'done'])->count();

        return round(($done / $total) * 100, 1);
    }

    public function currentWeek(): int
    {
        return (int) max(1, ceil($this->start_date->diffInDays(now()) / 7));
    }
}
