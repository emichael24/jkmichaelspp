<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Meeting extends Model
{
    protected $fillable = [
        'uuid',
        'title',
        'description',
        'organizer_id',
        'meetable_type',
        'meetable_id',
        'type',
        'platform',
        'meeting_url',
        'meeting_id_external',
        'scheduled_at',
        'duration_minutes',
        'agenda',
        'notes',
        'action_items',
        'replay_url',
        'transcript',
        'status',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'action_items' => 'array',
    ];

    /* ── Boot ──────────────────────────────────────── */

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Meeting $meeting) {
            if (empty($meeting->uuid)) {
                $meeting->uuid = (string) Str::uuid();
            }
        });
    }

    /* ── Relationships ─────────────────────────────── */

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function meetable()
    {
        return $this->morphTo();
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(MeetingAttendee::class);
    }

    public function attendeeUsers()
    {
        return $this->belongsToMany(User::class, 'meeting_attendees')
            ->withPivot('rsvp', 'attended', 'joined_at', 'left_at')
            ->withTimestamps();
    }

    /* ── Scopes ────────────────────────────────────── */

    public function scopeUpcoming($query)
    {
        return $query->where('scheduled_at', '>=', now())
            ->where('status', 'scheduled')
            ->orderBy('scheduled_at');
    }

    public function scopeForOrganizer($query, int $userId)
    {
        return $query->where('organizer_id', $userId);
    }

    public function scopeForCohort($query, int $cohortId)
    {
        return $query->where('meetable_type', Cohort::class)
            ->where('meetable_id', $cohortId);
    }

    /* ── Helpers ───────────────────────────────────── */

    public function isPast(): bool
    {
        return $this->scheduled_at->addMinutes($this->duration_minutes)->isPast();
    }

    public function attendedCount(): int
    {
        return $this->attendees()->where('attended', true)->count();
    }
}
