<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventRegistration extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'event_id',
        'user_id',
        'attended',
        'registered_at',
        'created_at',
    ];

    protected $casts = [
        'attended' => 'boolean',
        'registered_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markAttended(): void
    {
        $this->update(['attended' => true]);
    }
}
