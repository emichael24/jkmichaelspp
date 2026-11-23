<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailTemplate extends Model
{
    public const CATEGORIES = ['general', 'newsletter', 'announcement', 'event', 'follow_up', 'welcome', 'reminder'];

    protected $fillable = ['name', 'category', 'subject', 'body', 'created_by'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
