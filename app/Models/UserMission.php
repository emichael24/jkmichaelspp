<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMission extends Model
{
    protected $fillable = [
        'user_id',
        'mission_id',
        'progress',
        'target',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'progress' => 'integer',
        'target' => 'integer',
        'completed_at' => 'datetime',
    ];

    // ── Relationships ────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    // ── Scopes ───────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    // ── Helpers ──────────────────────────────────

    public function progressPct(): int
    {
        if ($this->target <= 0) {
            return 100;
        }

        return min(100, (int) round(($this->progress / $this->target) * 100));
    }

    /**
     * Increment progress toward mission completion.
     */
    public function advance(int $amount = 1): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $this->progress = min($this->progress + $amount, $this->target);

        if ($this->progress >= $this->target) {
            $this->status = 'completed';
            $this->completed_at = now();
        }

        $this->save();

        return $this->status === 'completed';
    }
}
