<?php

namespace App\Models;

use App\Services\CommunityAccessService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DirectMessage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'sender_id', 'campaign_id', 'delivery_id',
        'recipient_id',
        'body',
        'attachment_url',
        'read_at',
        'created_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function markAsRead(): void
    {
        if (! $this->read_at) {
            $this->update(['read_at' => now()]);
        }
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Get conversation between two users.
     */
    public function scopeConversation($query, int $userId1, int $userId2)
    {
        return $query->where(fn ($conversation) => $conversation
            ->where(fn ($q) => $q->where('sender_id', $userId1)->where('recipient_id', $userId2))
            ->orWhere(fn ($q) => $q->where('sender_id', $userId2)->where('recipient_id', $userId1)))
            ->orderBy('created_at');
    }

    /**
     * Get unread count for a user.
     */
    public static function unreadCountFor(int $userId): int
    {
        $peers = app(CommunityAccessService::class)->peers(User::findOrFail($userId))->select('id');

        return self::where('recipient_id', $userId)->whereIn('sender_id', $peers)->whereNull('read_at')->count();
    }

    /**
     * Get latest conversations for a user.
     */
    public static function conversationsFor(int $userId)
    {
        $peers = app(CommunityAccessService::class)->peers(User::findOrFail($userId))->select('id');

        return self::where(fn ($q) => $q->where('sender_id', $userId)->whereIn('recipient_id', clone $peers))
            ->orWhere(fn ($q) => $q->where('recipient_id', $userId)->whereIn('sender_id', clone $peers))
            ->orderByDesc('created_at')
            ->get()
            ->groupBy(function ($msg) use ($userId) {
                return $msg->sender_id === $userId ? $msg->recipient_id : $msg->sender_id;
            })
            ->map(fn ($messages) => $messages->first());
    }
}
