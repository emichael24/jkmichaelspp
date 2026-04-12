<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Badge extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'category',
        'criteria',
        'xp_reward',
        'is_active',
    ];

    protected $casts = [
        'criteria'  => 'array',
        'xp_reward' => 'integer',
        'is_active' => 'boolean',
    ];

    // ── Relationships ────────────────────────────

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_badges')
            ->withPivot('earned_at')
            ->withTimestamps();
    }

    // ── Scopes ───────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    // ── Helpers ──────────────────────────────────

    /**
     * Check if a user has earned this badge.
     */
    public function isEarnedBy(int $userId): bool
    {
        return \DB::table('user_badges')
            ->where('user_id', $userId)
            ->where('badge_id', $this->id)
            ->exists();
    }
}
