<?php

namespace App\Models;

use App\Models\Traits\RecordsDomainAudit;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use RecordsDomainAudit;

    protected $guarded = [];

    protected $hidden = ['provider_email_token'];

    protected $casts = [
        'provider_email_token' => 'encrypted',
        'last_paid_at' => 'datetime',
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
        return ($this->status === 'active' &&
                ($this->ends_at === null || $this->ends_at->isFuture()))
            || $this->isTrialing();
    }

    public function isTrialing()
    {
        return $this->status === 'trialing' &&
               $this->trial_ends_at !== null &&
               $this->trial_ends_at->isFuture();
    }
}
