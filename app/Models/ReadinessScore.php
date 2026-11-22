<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadinessScore extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'course_id',
        'score_pct',
        'confidence_band',
        'pass_likelihood',
        'domain_breakdown',
        'weak_areas',
        'calculated_at',
        'created_at',
    ];

    protected $casts = [
        'score_pct' => 'decimal:2',
        'domain_breakdown' => 'array',
        'weak_areas' => 'array',
        'calculated_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    // ── Relationships ────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    // ── Scopes ───────────────────────────────────

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeLatest($query)
    {
        return $query->orderByDesc('calculated_at');
    }

    // ── Helpers ──────────────────────────────────

    public static function latestFor(int $userId, int $courseId): ?self
    {
        return static::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->orderByDesc('calculated_at')
            ->first();
    }
}
