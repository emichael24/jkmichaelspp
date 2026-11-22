<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingAttendee extends Model
{
    protected $fillable = [
        'meeting_id',
        'user_id',
        'rsvp',
        'attended',
        'joined_at',
        'left_at',
    ];

    protected $casts = [
        'attended' => 'boolean',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
    ];

    /* ── Relationships ─────────────────────────────── */

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /* ── Scopes ────────────────────────────────────── */

    public function scopeAttended($query)
    {
        return $query->where('attended', true);
    }

    public function scopeAccepted($query)
    {
        return $query->where('rsvp', 'accepted');
    }
}
