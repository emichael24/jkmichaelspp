<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use App\Services\PaystackService;
use App\Services\SubscriptionBillingService;
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
                'cancels_at' => $subscription->cancels_at?->toISOString(),
                'payment_provider' => $subscription->payment_provider,
            ] : null,
        ]);
    }

    public function subscribe(Request $request, Plan $plan)
    {
        $user = $request->user();

        if (! $plan->is_active) {
            abort(404);
        }

        abort_if((float) $plan->price > 0, 422, 'Paid plans require a verified payment. Use the payment checkout.');

        $current = $user->activeSubscription();
        if ($current && $current->plan_id === $plan->id) {
            return back()->with('info', 'You already have this subscription.');
        }

        abort_if($user->subscriptions()->where('payment_provider', 'paystack')->whereNull('canceled_at')->exists(), 409, 'Cancel the existing recurring subscription first.');
        $user->subscribeTo($plan, 'manual', null);

        return back()->with('success', 'Subscription activated successfully.');
    }

    public function initializePaystack(Request $request, Plan $plan)
    {
        return response()->json(app(SubscriptionBillingService::class)->checkout($request->user(), $plan));
    }

    public function paystackCallback(Request $request)
    {
        $request->validate(['reference' => 'required|string|max:100']);
        $subscription = app(SubscriptionBillingService::class)->confirm($request->string('reference')->toString(), $request->user());
        abort_unless($subscription, 404);

        return redirect()->route('subscriptions.index')->with('success', 'Payment verified. Your subscription is active.');
    }

    public function cancel(Request $request, Subscription $subscription)
    {
        app(SubscriptionBillingService::class)->cancel($subscription, $request->user());

        return back()->with('success', 'Renewal canceled. Access remains available until the end of your paid period.');
    }
}
