<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubscriptionController extends Controller
{
    public function __construct(private PaystackService $paystack) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $plans = Plan::with('features')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $subscription = $user?->activeSubscription();

        return Inertia::render('Subscriptions/Index', [
            'plans' => $plans,
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'plan_id' => $subscription->plan_id,
                'name' => $subscription->name,
                'status' => $subscription->status,
                'trial_ends_at' => $subscription->trial_ends_at?->toISOString(),
                'starts_at' => $subscription->starts_at?->toISOString(),
                'ends_at' => $subscription->ends_at?->toISOString(),
            ] : null,
        ]);
    }

    public function subscribe(Request $request, Plan $plan)
    {
        $user = $request->user();

        if (! $plan->is_active) {
            abort(404);
        }

        $current = $user->activeSubscription();
        if ($current && $current->plan_id === $plan->id) {
            return back()->with('info', 'You already have this subscription.');
        }

        $user->subscribeTo($plan, 'manual', null);

        return back()->with('success', 'Subscription activated successfully.');
    }

    public function initializePaystack(Request $request, Plan $plan)
    {
        $user = $request->user();

        if (! $plan->is_active) {
            abort(404);
        }

        if (empty($plan->paystack_plan_code)) {
            return response()->json(['error' => 'Paystack plan code not configured.'], 422);
        }

        $amount = (float) $plan->price;
        if ($amount <= 0) {
            $user->subscribeTo($plan, 'paystack', null);
            return response()->json(['redirect' => route('subscriptions.index')]);
        }

        $callbackUrl = route('subscriptions.paystack.callback');

        $result = $this->paystack->initializeTransaction([
            'email' => $user->email,
            'amount' => $amount,
            'currency' => $plan->currency ?? 'NGN',
            'callback_url' => $callbackUrl,
            'plan' => $plan->paystack_plan_code,
            'metadata' => [
                'is_subscription' => true,
                'plan_id' => $plan->id,
                'user_id' => $user->id,
            ],
        ]);

        return response()->json([
            'reference' => $result['reference'],
            'authorization_url' => $result['authorization_url'],
        ]);
    }

    public function paystackCallback(Request $request)
    {
        $reference = $request->get('reference') ?? $request->get('trxref');
        if (! $reference) {
            return redirect()->route('subscriptions.index')->with('error', 'Invalid payment reference.');
        }

        try {
            $data = $this->paystack->verifyTransaction($reference);
        } catch (\Throwable $e) {
            return redirect()->route('subscriptions.index')->with('error', 'Payment verification failed.');
        }

        if (($data['status'] ?? '') !== 'success') {
            return redirect()->route('subscriptions.index')->with('error', 'Payment was not successful.');
        }

        $metadata = $data['metadata'] ?? [];
        $planId = $metadata['plan_id'] ?? null;
        $userId = $metadata['user_id'] ?? null;

        if (! $planId || ! $userId || (int) $userId !== (int) $request->user()->id) {
            return redirect()->route('subscriptions.index')->with('error', 'Unable to verify subscription purchase.');
        }

        $plan = Plan::find($planId);
        if (! $plan) {
            return redirect()->route('subscriptions.index')->with('error', 'Subscription plan not found.');
        }

        if (empty($plan->paystack_plan_code)) {
            return redirect()->route('subscriptions.index')->with('error', 'Paystack plan code not configured.');
        }

        $customerCode = $data['customer']['customer_code'] ?? null;
        if (! $customerCode) {
            try {
                $customer = $this->paystack->createCustomer([
                    'email' => $request->user()->email,
                    'first_name' => $request->user()->first_name,
                    'last_name' => $request->user()->last_name,
                    'phone' => $request->user()->phone ?? null,
                ]);
                $customerCode = $customer['customer_code'] ?? null;
            } catch (\Throwable $e) {
                return redirect()->route('subscriptions.index')->with('error', 'Unable to create Paystack customer.');
            }
        }

        if (! $customerCode) {
            return redirect()->route('subscriptions.index')->with('error', 'Unable to resolve Paystack customer.');
        }

        try {
            $subscription = $this->paystack->createSubscription($customerCode, $plan->paystack_plan_code);
        } catch (\Throwable $e) {
            return redirect()->route('subscriptions.index')->with('error', 'Unable to create Paystack subscription.');
        }

        $providerId = $subscription['subscription_code'] ?? null;
        if (! $providerId) {
            return redirect()->route('subscriptions.index')->with('error', 'Subscription code missing from Paystack.');
        }

        $request->user()->subscribeTo($plan, 'paystack', $providerId);

        return redirect()->route('subscriptions.index')->with('success', 'Subscription activated successfully.');
    }
}
