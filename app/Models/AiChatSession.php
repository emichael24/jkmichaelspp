<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiChatSession extends Model
{
    protected $fillable = ['uuid', 'user_id', 'title', 'context_type', 'context_id', 'messages', 'is_archived'];

    protected $casts = ['messages' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
