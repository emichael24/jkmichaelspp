<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Certificate extends Model
{
    protected $guarded = [];

    protected $casts = [
        'metadata'   => 'array',
        'issued_at'  => 'datetime',
        'expires_at' => 'datetime',
        'is_revoked' => 'boolean',
    ];

    /* ── Boot ──────────────────────────────────────── */

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $cert) {
            if (empty($cert->uuid)) {
                $cert->uuid = (string) Str::uuid();
            }
            if (empty($cert->certificate_number)) {
                $cert->certificate_number = 'JKM-' . date('Y') . '-' . strtoupper(Str::random(5));
            }
        });
    }

    /* ── Relationships ─────────────────────────────── */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function certifiable()
    {
        return $this->morphTo();
    }

    /* ── Scopes ────────────────────────────────────── */

    public function scopeValid($q)
    {
        return $q->where('is_revoked', false);
    }

    public function scopeForUser($q, int $userId)
    {
        return $q->where('user_id', $userId);
    }

    /* ── Helpers ───────────────────────────────────── */

    public function isValid(): bool
    {
        if ($this->is_revoked) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        return true;
    }
}
