<?php

namespace App\Models\Traits;

use App\Models\Plan;
use App\Models\Subscription;

trait HasSubscriptions
{
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription()
    {
        return $this->subscriptions()
            ->whereIn('status', ['active', 'trialing'])
            ->where(function ($query) {
                $query->where(function ($activeQuery) {
                    $activeQuery->where('status', 'active')
                        ->where(function ($dateQuery) {
                            $dateQuery->whereNull('ends_at')
                                ->orWhere('ends_at', '>', now());
                        });
                })->orWhere(function ($trialQuery) {
                    $trialQuery->where('status', 'trialing')
                        ->where('trial_ends_at', '>', now());
                });
            })
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
        if (! $plan) {
            return false;
        }

        return $plan->features()->where('slug', $featureSlug)->exists();
    }

    public function getFeatureValue($featureSlug)
    {
        $plan = $this->plan();
        if (! $plan) {
            return null;
        }

        $feature = $plan->features()->where('slug', $featureSlug)->first();

        return $feature ? $feature->value : null;
    }

    public function subscribeTo(Plan $plan, $paymentProvider = null, $paymentProviderId = null)
    {
        // Cancel existing active subscriptions
        $this->subscriptions()->whereIn('status', ['active', 'trialing'])->update([
            'status' => 'canceled',
            'canceled_at' => now(),
            'ends_at' => now(), // Or end of cycle
        ]);

        $hasTrial = (float) $plan->price === 0.0 && (int) $plan->trial_period_days > 0 && ! $this->subscriptions()->exists();
        $startsAt = now();
        $trialEndsAt = $hasTrial ? $startsAt->copy()->addDays((int) $plan->trial_period_days) : null;
        $endsAt = $trialEndsAt
            ? $this->resolveCycleEnd($plan, $trialEndsAt)
            : $this->resolveCycleEnd($plan, $startsAt);

        return $this->subscriptions()->create([
            'plan_id' => $plan->id,
            'name' => $plan->name,
            'payment_provider' => $paymentProvider,
            'payment_provider_id' => $paymentProviderId,
            'status' => $hasTrial ? 'trialing' : 'active',
            'trial_ends_at' => $trialEndsAt,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }

    private function resolveCycleEnd(Plan $plan, $anchor)
    {
        $interval = max((int) ($plan->billing_interval ?? 1), 1);

        return $plan->billing_period === 'year'
            ? $anchor->copy()->addYears($interval)
            : $anchor->copy()->addMonths($interval);
    }
}
