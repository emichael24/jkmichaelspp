<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMastery extends Model
{
    protected $table = 'user_mastery';

    protected $fillable = [
        'user_id',
        'course_id',
        'domain',
        'subtopic',
        'accuracy_pct',
        'confidence_score',
        'speed_score',
        'consistency_score',
        'improvement_rate',
        'mastery_level',
        'total_attempts',
        'last_attempt_at',
    ];

    protected $casts = [
        'accuracy_pct' => 'decimal:2',
        'confidence_score' => 'decimal:2',
        'speed_score' => 'decimal:2',
        'consistency_score' => 'decimal:2',
        'improvement_rate' => 'decimal:2',
        'total_attempts' => 'integer',
        'last_attempt_at' => 'datetime',
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

    public function scopeForCourse($query, int $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    public function scopeWeak($query, float $threshold = 50)
    {
        return $query->where('accuracy_pct', '<', $threshold);
    }

    public function scopeStrong($query, float $threshold = 75)
    {
        return $query->where('accuracy_pct', '>=', $threshold);
    }

    // ── Helpers ──────────────────────────────────

    /**
     * Recalculate mastery level based on accuracy.
     */
    public function recalculateLevel(): void
    {
        $this->mastery_level = match (true) {
            $this->accuracy_pct >= 90 => 'expert',
            $this->accuracy_pct >= 70 => 'proficient',
            $this->accuracy_pct >= 40 => 'developing',
            default => 'novice',
        };
    }

    /**
     * Composite mastery score (weighted blend).
     */
    public function compositeScore(): float
    {
        return round(
            ($this->accuracy_pct * 0.40) +
            ($this->confidence_score * 0.15) +
            ($this->speed_score * 0.20) +
            ($this->consistency_score * 0.15) +
            ($this->improvement_rate * 0.10),
            2
        );
    }
}
