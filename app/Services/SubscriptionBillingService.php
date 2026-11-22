<?php

namespace App\Services;

use App\Models\BillingWebhookEvent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionCheckout;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubscriptionBillingService
{
    public function __construct(private PaystackService $paystack) {}

    public function checkout(User $user, Plan $plan): array
    {
        abort_unless($plan->is_active && $plan->price > 0 && $plan->paystack_plan_code, 422, 'This plan is unavailable for payment.');
        $intent = DB::transaction(function () use ($user, $plan) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_if($user->subscriptions()->where('payment_provider', 'paystack')->whereNull('canceled_at')->exists(), 409, 'Manage your existing subscription before starting another.');
            $pending = SubscriptionCheckout::where('user_id', $user->id)->where('status', 'pending')->where('expires_at', '>', now())->first();
            if ($pending) {
                abort_unless($pending->plan_id === $plan->id, 409, 'Finish or let your existing checkout expire first.');

                return $pending;
            }

            return SubscriptionCheckout::create([
                'payment_mode' => app(PaystackSettings::class)->mode(), 'reference' => 'JKMS-'.Str::uuid(), 'user_id' => $user->id, 'plan_id' => $plan->id,
                'amount_minor' => (int) round($plan->price * 100), 'currency' => strtoupper($plan->currency ?? 'NGN'),
                'plan_code' => $plan->paystack_plan_code, 'email' => $user->email,
                'billing_period' => $plan->billing_period, 'billing_interval' => max(1, $plan->billing_interval),
                'expires_at' => now()->addDay(),
            ]);
        }, 3);
        abort_if($intent->payment_mode && $intent->payment_mode !== app(PaystackSettings::class)->mode(), 409, 'This subscription checkout belongs to a previous payment mode. Let it expire before starting a new checkout.');
        if ($intent->authorization_url) {
            return ['reference' => $intent->reference, 'authorization_url' => $intent->authorization_url];
        }
        $result = $this->paystack->initializeTransaction([
            'payment_mode' => $intent->payment_mode, 'reference' => $intent->reference, 'email' => $intent->email, 'amount' => $intent->amount_minor / 100,
            'currency' => $intent->currency, 'plan' => $intent->plan_code, 'channels' => ['card'],
            'callback_url' => route('subscriptions.paystack.callback'),
            'metadata' => ['is_subscription' => true, 'checkout_reference' => $intent->reference],
        ]);
        $intent->update(['authorization_url' => $result['authorization_url']]);

        return $result;
    }

    public function confirm(string $reference, ?User $actor = null, ?string $signedMode = null): ?Subscription
    {
        $intent = SubscriptionCheckout::find($reference);
        if (! $intent || ($signedMode && $intent->payment_mode && $signedMode !== $intent->payment_mode)) {
            return null;
        }
        abort_if($actor && $actor->id !== $intent->user_id, 403);
        if ($intent->status === 'paid') {
            return Subscription::findOrFail($intent->subscription_id);
        }
        $payment = $this->paystack->verifyTransaction($reference, $intent->payment_mode);
        $this->assertPayment($payment, $reference, $intent->amount_minor, $intent->currency, $intent->email);
        $providerPlan = data_get($payment, 'plan_object.plan_code') ?: (is_array($payment['plan'] ?? null) ? data_get($payment, 'plan.plan_code') : ($payment['plan'] ?? null));
        abort_unless($providerPlan === $intent->plan_code, 422, 'Payment plan does not match the checkout.');
        $subscription = DB::transaction(function () use ($intent, $payment) {
            User::whereKey($intent->user_id)->lockForUpdate()->firstOrFail();
            $intent = SubscriptionCheckout::whereKey($intent->reference)->lockForUpdate()->firstOrFail();
            if ($intent->status === 'paid') {
                return Subscription::findOrFail($intent->subscription_id);
            }
            $paidAt = Carbon::parse($payment['paid_at']);
            $end = $intent->billing_period === 'year' ? $paidAt->copy()->addYearsNoOverflow($intent->billing_interval) : $paidAt->copy()->addMonthsNoOverflow($intent->billing_interval);
            $subscription = Subscription::create([
                'user_id' => $intent->user_id, 'plan_id' => $intent->plan_id, 'name' => Plan::findOrFail($intent->plan_id)->name,
                'payment_mode' => $intent->payment_mode ?: app(PaystackSettings::class)->mode(), 'payment_provider' => 'paystack', 'status' => 'active', 'starts_at' => $paidAt, 'ends_at' => $end,
                'amount_minor' => $intent->amount_minor, 'currency' => $intent->currency,
                'provider_customer_code' => $payment['customer']['customer_code'], 'provider_plan_code' => $intent->plan_code, 'last_paid_at' => $paidAt,
            ]);
            DB::table('billing_receipts')->insert(['reference' => $intent->reference, 'subscription_id' => $subscription->id, 'amount_minor' => $intent->amount_minor,
                'currency' => $intent->currency, 'paid_at' => $paidAt, 'period_end' => $end, 'created_at' => now(), 'updated_at' => now()]);
            $intent->update(['status' => 'paid', 'paid_at' => $paidAt, 'subscription_id' => $subscription->id]);

            return $subscription;
        }, 3);
        $this->retryPending();

        return $subscription->fresh();
    }

    public function webhook(array $payload, ?string $mode = null): void
    {
        $mode ??= app(PaystackSettings::class)->mode();
        $event = BillingWebhookEvent::firstOrCreate(['id' => hash('sha256', $mode.'|'.json_encode($payload))], ['event' => $payload['event'], 'payload' => $payload, 'payment_mode' => $mode]);
        $this->process($event);
    }

    public function retryPending(): int
    {
        $count = 0;
        foreach (BillingWebhookEvent::whereNull('processed_at')->orderBy('created_at')->limit(100)->get() as $event) {
            $this->process($event);
            $count++;
        }

        return $count;
    }

    private function process(BillingWebhookEvent $event): void
    {
        if ($event->processed_at) {
            return;
        }
        $data = $event->payload['data'] ?? [];
        $code = data_get($data, 'subscription.subscription_code') ?: ($data['subscription_code'] ?? null);
        if (! is_string($code) || ! str_starts_with($code, 'SUB_')) {
            return;
        }
        // Fetch current provider state so an old delivery cannot reverse a newer cancellation.
        $remote = $this->paystack->fetchSubscription($code, $event->payment_mode);
        DB::transaction(function () use ($event, $data, $code, $remote) {
            $lockedEvent = BillingWebhookEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            if ($lockedEvent->processed_at) {
                return;
            }
            $subscription = Subscription::where('payment_provider', 'paystack')->when($event->payment_mode, fn ($q) => $q->where('payment_mode', $event->payment_mode))->where('payment_provider_id', $code)->lockForUpdate()->first();
            if (! $subscription) {
                $matches = Subscription::where('payment_provider', 'paystack')->when($event->payment_mode, fn ($q) => $q->where('payment_mode', $event->payment_mode))->whereNull('payment_provider_id')
                    ->where('provider_customer_code', data_get($remote, 'customer.customer_code'))
                    ->where('provider_plan_code', data_get($remote, 'plan.plan_code'))->lockForUpdate()->get();
                if ($matches->count() !== 1) {
                    return;
                }
                $subscription = $matches->first();
                abort_unless((int) ($remote['amount'] ?? -1) === (int) $subscription->amount_minor, 422);
                $subscription->payment_provider_id = $code;
            }
            abort_unless(data_get($remote, 'customer.customer_code') === $subscription->provider_customer_code && data_get($remote, 'plan.plan_code') === $subscription->provider_plan_code, 422);
            $subscription->provider_email_token = $remote['email_token'] ?? $subscription->provider_email_token;
            if ($event->event === 'invoice.update' && ($data['paid'] ?? false) && ($data['status'] ?? '') === 'success') {
                $reference = data_get($data, 'transaction.reference');
                abort_unless(is_string($reference), 422);
                if (! DB::table('billing_receipts')->where('reference', $reference)->exists()) {
                    $payment = $this->paystack->verifyTransaction($reference, $subscription->payment_mode);
                    $this->assertPayment($payment, $reference, $subscription->amount_minor, $subscription->currency, data_get($remote, 'customer.email'));
                    abort_unless(data_get($payment, 'customer.customer_code') === $subscription->provider_customer_code, 422);
                    $paidAt = Carbon::parse($payment['paid_at']);
                    $end = Carbon::parse($data['period_end']);
                    abort_unless($end->greaterThan($paidAt) && $end->lessThan($paidAt->copy()->addYears(6)), 422);
                    DB::table('billing_receipts')->insert(['reference' => $reference, 'subscription_id' => $subscription->id, 'amount_minor' => $subscription->amount_minor,
                        'currency' => $subscription->currency, 'paid_at' => $paidAt, 'period_end' => $end, 'created_at' => now(), 'updated_at' => now()]);
                    if (! $subscription->last_paid_at || $paidAt->greaterThan($subscription->last_paid_at)) {
                        $subscription->last_paid_at = $paidAt;
                        $subscription->ends_at = $subscription->ends_at?->greaterThan($end) ? $subscription->ends_at : $end;
                    }
                }
            }
            $remoteStatus = $remote['status'] ?? '';
            $subscription->status = $subscription->ends_at?->isFuture() ? 'active' : ($remoteStatus === 'attention' ? 'past_due' : 'canceled');
            if (in_array($remoteStatus, ['non-renewing', 'cancelled', 'completed', 'complete'], true)) {
                $subscription->cancels_at = $subscription->ends_at;
                $subscription->canceled_at ??= now();
            }
            $subscription->save();
            $lockedEvent->update(['processed_at' => now()]);
        }, 3);
    }

    public function cancel(Subscription $subscription, User $user): void
    {
        abort_unless($subscription->user_id === $user->id, 403);
        abort_unless($subscription->payment_provider_id && $subscription->provider_email_token, 409, 'Provider confirmation is pending. Please retry shortly.');
        if ($subscription->canceled_at) {
            return;
        }
        $this->paystack->disableSubscription($subscription->payment_provider_id, $subscription->provider_email_token, $subscription->payment_mode);
        $subscription->update(['canceled_at' => now(), 'cancels_at' => $subscription->ends_at]);
    }

    private function assertPayment(array $payment, string $reference, int $amount, string $currency, ?string $email): void
    {
        abort_unless(($payment['status'] ?? '') === 'success' && ($payment['reference'] ?? '') === $reference
            && (int) ($payment['amount'] ?? -1) === $amount && strtoupper($payment['currency'] ?? '') === strtoupper($currency)
            && strtolower(data_get($payment, 'customer.email', '')) === strtolower($email ?? '')
            && data_get($payment, 'customer.customer_code') && ! empty($payment['paid_at']), 422, 'The verified payment does not match the purchase.');
    }
}
