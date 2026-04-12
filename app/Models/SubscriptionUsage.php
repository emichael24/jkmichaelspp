<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionUsage extends Model
{
    protected $guarded = [];

    protected $casts = [
        'valid_until' => 'datetime',
    ];

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function feature()
    {
        return $this->belongsTo(PlanFeature::class);
    }
}
