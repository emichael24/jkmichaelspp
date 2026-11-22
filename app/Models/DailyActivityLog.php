<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'lessons_completed',
        'quizzes_completed',
        'questions_answered',
        'questions_correct',
        'time_spent_minutes',
        'xp_earned',
        'simulations_completed',
        'flashcards_reviewed',
    ];

    protected $casts = [
        'date' => 'datetime:Y-m-d',
        'lessons_completed' => 'integer',
        'quizzes_completed' => 'integer',
        'questions_answered' => 'integer',
        'questions_correct' => 'integer',
        'time_spent_minutes' => 'integer',
        'xp_earned' => 'integer',
        'simulations_completed' => 'integer',
        'flashcards_reviewed' => 'integer',
    ];

    // ── Relationships ────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ───────────────────────────────────

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeInRange($query, $start, $end)
    {
        return $query->whereBetween('date', [$start, $end]);
    }

    // ── Helpers ──────────────────────────────────

    /**
     * Get or create today's log for a user.
     */
    public static function today(int $userId): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId, 'date' => now()->toDateString()],
        );
    }

    /**
     * Increment a single counter.
     */
    public function incrementCounter(string $field, int $amount = 1): self
    {
        $this->$field += $amount;
        $this->save();

        return $this;
    }
}
