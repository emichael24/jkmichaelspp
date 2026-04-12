<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    protected $fillable = [
        'title',
        'description',
        'type',
        'thumbnail',
        'speaker_name',
        'speaker_bio',
        'platform',
        'meeting_url',
        'scheduled_at',
        'duration_minutes',
        'max_registrations',
        'price',
        'replay_url',
        'is_published',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'price' => 'decimal:2',
        'is_published' => 'boolean',
    ];

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function isFree(): bool
    {
        return $this->price == 0;
    }

    public function isFull(): bool
    {
        if (!$this->max_registrations) {
            return false;
        }
        return $this->registrations()->count() >= $this->max_registrations;
    }

    public function isUpcoming(): bool
    {
        return $this->scheduled_at->isFuture();
    }

    public function isPast(): bool
    {
        return $this->scheduled_at->isPast();
    }

    public function hasReplay(): bool
    {
        return !empty($this->replay_url);
    }

    public function getRegistrationCount(): int
    {
        return $this->registrations()->count();
    }

    public function getSpotsLeft(): ?int
    {
        if (!$this->max_registrations) {
            return null;
        }
        return max(0, $this->max_registrations - $this->getRegistrationCount());
    }

    public function isRegistered(int $userId): bool
    {
        return $this->registrations()->where('user_id', $userId)->exists();
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('scheduled_at', '>', now())->orderBy('scheduled_at');
    }

    public function scopePast($query)
    {
        return $query->where('scheduled_at', '<', now())->orderByDesc('scheduled_at');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
