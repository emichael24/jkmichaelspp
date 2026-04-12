<?php

namespace App\Models\Traits;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\PlanFeature;

trait HasSubscriptions
{
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription()
    {
        return $this->subscriptions()
            ->where(function ($query) {
                $query->whereNull('ends_at')
                      ->orWhere('ends_at', '>', now());
            })
            ->where('status', 'active')
            ->latest('id')
            ->first();
    }

    public function plan()
    {
        $subscription = $this->activeSubscription();
        return $subscription ? $subscription->plan : null;
    }

    public function hasFeature($featureSlug)
    {
        $plan = $this->plan();
        if (!$plan) {
            return false;
        }

        return $plan->features()->where('slug', $featureSlug)->exists();
    }

    public function getFeatureValue($featureSlug)
    {
        $plan = $this->plan();
        if (!$plan) {
            return null;
        }

        $feature = $plan->features()->where('slug', $featureSlug)->first();
        return $feature ? $feature->value : null;
    }

    public function subscribeTo(Plan $plan, $paymentProvider = null, $paymentProviderId = null)
    {
        // Cancel existing active subscriptions
        $this->subscriptions()->where('status', 'active')->update([
            'status' => 'canceled',
            'canceled_at' => now(),
            'ends_at' => now(), // Or end of cycle
        ]);

        return $this->subscriptions()->create([
            'plan_id' => $plan->id,
            'name' => $plan->name,
            'payment_provider' => $paymentProvider,
            'payment_provider_id' => $paymentProviderId,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => $plan->billing_period === 'year' ? now()->addYear() : now()->addMonth(),
        ]);
    }
}
