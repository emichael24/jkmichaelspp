<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $guarded = [];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'cancels_at' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function usages()
    {
        return $this->hasMany(SubscriptionUsage::class);
    }

    public function isActive()
    {
        return $this->status === 'active' &&
               ($this->ends_at === null || $this->ends_at->isFuture());
    }

    public function isTrialing()
    {
        return $this->status === 'trialing' &&
               $this->trial_ends_at !== null &&
               $this->trial_ends_at->isFuture();
    }
}
