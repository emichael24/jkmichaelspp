<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $table = 'subscription_plans';

    protected $guarded = [];

    public function features()
    {
        return $this->hasMany(PlanFeature::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
}
