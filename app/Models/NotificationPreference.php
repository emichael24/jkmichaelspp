<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'push_enabled',
        'email_enabled',
        'quiet_hours_start',
        'quiet_hours_end',
        'learning_reminders',
        'internship_reminders',
        'announcements',
        'frequency',
    ];

    protected $casts = [
        'push_enabled' => 'boolean',
        'email_enabled' => 'boolean',
        'learning_reminders' => 'boolean',
        'internship_reminders' => 'boolean',
        'announcements' => 'boolean',
    ];

    // ── Relationships ──

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Helpers ──

    public function isInQuietHours(): bool
    {
        if (!$this->quiet_hours_start || !$this->quiet_hours_end) {
            return false;
        }

        $now = now()->format('H:i:s');
        $start = $this->quiet_hours_start;
        $end = $this->quiet_hours_end;

        // Handle overnight quiet hours (e.g., 22:00 to 07:00)
        if ($start > $end) {
            return $now >= $start || $now <= $end;
        }

        return $now >= $start && $now <= $end;
    }

    public function shouldSendPush(string $category = 'learning'): bool
    {
        if (!$this->push_enabled) return false;
        if ($this->isInQuietHours()) return false;
        return $this->isCategoryEnabled($category);
    }

    public function shouldSendEmail(string $category = 'learning'): bool
    {
        if (!$this->email_enabled) return false;
        return $this->isCategoryEnabled($category);
    }

    private function isCategoryEnabled(string $category): bool
    {
        return match ($category) {
            'learning' => $this->learning_reminders,
            'internship' => $this->internship_reminders,
            'admin', 'system' => $this->announcements,
            default => true,
        };
    }
}
