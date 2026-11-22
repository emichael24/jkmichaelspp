<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserGamification extends Model
{
    protected $table = 'user_gamification';

    protected $fillable = [
        'user_id',
        'total_xp',
        'level',
        'current_streak_days',
        'longest_streak_days',
        'streak_protection_tokens',
        'last_activity_date',
        'rest_day_used_this_week',
    ];

    protected $casts = [
        'total_xp' => 'integer',
        'level' => 'integer',
        'current_streak_days' => 'integer',
        'longest_streak_days' => 'integer',
        'streak_protection_tokens' => 'integer',
        'last_activity_date' => 'date',
        'rest_day_used_this_week' => 'boolean',
    ];

    // ── Level titles ─────────────────────────────

    public const LEVEL_TITLES = [
        1 => 'Novice',
        5 => 'Apprentice',
        10 => 'Practitioner',
        20 => 'Professional',
        30 => 'Expert',
        40 => 'Master',
        50 => 'Guru',
    ];

    // ── Relationships ────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Helpers ──────────────────────────────────

    /**
     * Get or create gamification record for user.
     */
    public static function forUser(int $userId): self
    {
        return static::firstOrCreate(['user_id' => $userId]);
    }

    /**
     * Current level title.
     */
    public function levelTitle(): string
    {
        $title = 'Novice';
        foreach (self::LEVEL_TITLES as $lvl => $t) {
            if ($this->level >= $lvl) {
                $title = $t;
            }
        }

        return $title;
    }

    /**
     * XP needed for next level.
     */
    public function xpForNextLevel(): int
    {
        $nextLevel = $this->level + 1;
        $xpLevel = \DB::table('xp_levels')->where('level', $nextLevel)->value('xp_required');

        return $xpLevel ?? ($this->level * 100 + 100);
    }

    /**
     * XP progress toward next level (0-100).
     */
    public function xpProgressPct(): int
    {
        $currentLevelXp = \DB::table('xp_levels')->where('level', $this->level)->value('xp_required') ?? 0;
        $nextLevelXp = $this->xpForNextLevel();
        $range = $nextLevelXp - $currentLevelXp;

        if ($range <= 0) {
            return 100;
        }

        return min(100, (int) round(($this->total_xp - $currentLevelXp) / $range * 100));
    }

    /**
     * Check if streak needs updating (called on daily activity).
     */
    public function updateStreak(): array
    {
        $today = now()->toDateString();
        $result = ['streak_continued' => false, 'streak_lost' => false, 'protection_used' => false];

        if ($this->last_activity_date?->toDateString() === $today) {
            return $result; // Already logged today
        }

        $yesterday = now()->subDay()->toDateString();

        if ($this->last_activity_date?->toDateString() === $yesterday) {
            // Consecutive day
            $this->current_streak_days++;
            $result['streak_continued'] = true;
        } elseif ($this->last_activity_date && $this->last_activity_date->toDateString() < $yesterday) {
            // Missed day(s)
            if ($this->streak_protection_tokens > 0 && $this->current_streak_days > 0) {
                // Use protection token
                $this->streak_protection_tokens--;
                $this->current_streak_days++;
                $result['protection_used'] = true;
                $result['streak_continued'] = true;
            } else {
                // Streak lost
                $this->current_streak_days = 1;
                $result['streak_lost'] = true;
            }
        } else {
            // First ever activity
            $this->current_streak_days = 1;
            $result['streak_continued'] = true;
        }

        // Update longest
        if ($this->current_streak_days > $this->longest_streak_days) {
            $this->longest_streak_days = $this->current_streak_days;
        }

        // Award protection token every 7 days (max 3)
        if ($this->current_streak_days > 0 && $this->current_streak_days % 7 === 0 && $this->streak_protection_tokens < 3) {
            $this->streak_protection_tokens++;
        }

        $this->last_activity_date = $today;
        $this->save();

        return $result;
    }
}
