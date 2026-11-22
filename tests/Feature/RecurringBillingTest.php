<?php

namespace Tests\Feature;

use App\Models\BillingWebhookEvent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionCheckout;
use App\Models\User;
use App\Services\SubscriptionBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RecurringBillingTest extends TestCase
{
    use RefreshDatabase;

    private function intent(User $user): SubscriptionCheckout
    {
        $plan = Plan::create(['name' => 'Professional', 'slug' => 'professional', 'price' => 100, 'currency' => 'NGN', 'billing_period' => 'month', 'billing_interval' => 1, 'paystack_plan_code' => 'PLN_test', 'is_active' => true]);

        return SubscriptionCheckout::create(['reference' => 'JKMS-test', 'user_id' => $user->id, 'plan_id' => $plan->id, 'amount_minor' => 10000, 'currency' => 'NGN', 'email' => $user->email, 'plan_code' => 'PLN_test', 'billing_period' => 'month', 'billing_interval' => 1, 'expires_at' => now()->addDay()]);
    }

    public function test_verified_checkout_is_idempotent_and_does_not_create_a_second_provider_subscription(): void
    {
        $user = User::factory()->create();
        $intent = $this->intent($user);
        $payment = ['status' => 'success', 'reference' => $intent->reference, 'amount' => 10000, 'currency' => 'NGN', 'plan' => 'PLN_test', 'paid_at' => now()->toISOString(), 'customer' => ['email' => $user->email, 'customer_code' => 'CUS_test']];
        Http::fake(['*/transaction/verify/*' => Http::response(['status' => true, 'data' => $payment])]);
        $service = app(SubscriptionBillingService::class);
        $service->confirm($intent->reference, $user);
        $service->confirm($intent->reference, $user);
        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseCount('billing_receipts', 1);
        $this->assertTrue(Subscription::first()->ends_at->isFuture());
        Http::assertSentCount(1);
        $this->actingAs(User::factory()->create())->get('/subscriptions/paystack/callback?reference='.$intent->reference)->assertForbidden();
    }

    public function test_amount_mismatch_cannot_activate_subscription(): void
    {
        $user = User::factory()->create();
        $intent = $this->intent($user);
        Http::fake(['*/transaction/verify/*' => Http::response(['status' => true, 'data' => ['status' => 'success', 'reference' => $intent->reference, 'amount' => 1, 'currency' => 'NGN', 'paid_at' => now()->toISOString(), 'customer' => ['email' => $user->email, 'customer_code' => 'CUS_test']]])]);
        $this->actingAs($user)->get('/subscriptions/paystack/callback?reference='.$intent->reference)->assertUnprocessable();
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_out_of_order_subscription_event_renewal_and_cancellation_preserve_paid_access(): void
    {
        $user = User::factory()->create();
        $intent = $this->intent($user);
        $customer = ['email' => $user->email, 'customer_code' => 'CUS_test'];
        $remote = ['subscription_code' => 'SUB_test', 'customer' => $customer, 'plan' => ['plan_code' => 'PLN_test'], 'amount' => 10000, 'email_token' => 'provider-token', 'status' => 'active'];
        $payment = ['status' => 'success', 'reference' => $intent->reference, 'amount' => 10000, 'currency' => 'NGN', 'plan' => 'PLN_test', 'paid_at' => now()->toISOString(), 'customer' => $customer];
        Http::fake(function ($request) use (&$remote, &$payment) {
            return Http::response(['status' => true, 'data' => str_contains($request->url(), '/subscription/') ? $remote : $payment]);
        });
        $service = app(SubscriptionBillingService::class);
        $created = ['event' => 'subscription.create', 'data' => ['subscription_code' => 'SUB_test']];
        $service->webhook($created);
        $this->assertNull(BillingWebhookEvent::first()->processed_at);
        $subscription = $service->confirm($intent->reference, $user);
        $this->assertSame('SUB_test', $subscription->payment_provider_id);
        $this->assertNotNull(BillingWebhookEvent::first()->processed_at);
        $this->travel(1)->months();
        $payment['reference'] = 'renewal-1';
        $payment['paid_at'] = now()->toISOString();
        $remote['status'] = 'non-renewing';
        $invoice = ['event' => 'invoice.update', 'data' => ['subscription' => ['subscription_code' => 'SUB_test'], 'paid' => true, 'status' => 'success', 'transaction' => ['reference' => 'renewal-1'], 'period_end' => now()->addMonth()->toISOString()]];
        $service->webhook($invoice);
        $service->webhook($invoice);
        $this->assertDatabaseCount('billing_receipts', 2);
        $this->assertTrue($subscription->fresh()->isActive());
        $this->assertNotNull($subscription->fresh()->cancels_at);
        $this->assertArrayNotHasKey('provider_email_token', $subscription->fresh()->toArray());
        $this->travel(2)->months();
        $this->assertFalse($subscription->fresh()->isActive());
    }
}
