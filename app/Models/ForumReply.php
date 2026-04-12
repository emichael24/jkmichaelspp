<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ForumReply extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'thread_id',
        'user_id',
        'body',
        'parent_id',
        'is_accepted_answer',
    ];

    protected $casts = [
        'is_accepted_answer' => 'boolean',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ForumThread::class, 'thread_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ForumReply::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ForumReply::class, 'parent_id');
    }

    public function markAsAccepted(): void
    {
        // Unmark any previously accepted answer on this thread
        self::where('thread_id', $this->thread_id)
            ->where('is_accepted_answer', true)
            ->update(['is_accepted_answer' => false]);

        $this->update(['is_accepted_answer' => true]);
    }

    public function unmarkAsAccepted(): void
    {
        $this->update(['is_accepted_answer' => false]);
    }
}
