<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mission extends Model
{
    protected $fillable = [
        'title',
        'description',
        'type',
        'criteria',
        'xp_reward',
        'badge_reward_id',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected $casts = [
        'criteria'        => 'array',
        'xp_reward'       => 'integer',
        'starts_at'       => 'datetime',
        'ends_at'         => 'datetime',
        'is_active'       => 'boolean',
    ];

    // ── Relationships ────────────────────────────

    public function badgeReward(): BelongsTo
    {
        return $this->belongsTo(Badge::class, 'badge_reward_id');
    }

    public function userMissions(): HasMany
    {
        return $this->hasMany(UserMission::class);
    }

    // ── Scopes ───────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDaily($query)
    {
        return $query->where('type', 'daily');
    }

    public function scopeWeekly($query)
    {
        return $query->where('type', 'weekly');
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }
}
