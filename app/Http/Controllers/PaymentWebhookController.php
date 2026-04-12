<?php

namespace App\Http\Controllers;

use App\Services\PaymentService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private NotificationService $notificationService,
    ) {}

    /**
     * Paystack webhook handler.
     */
    public function paystack(Request $request)
    {
        // Verify signature
        $signature = $request->header('x-paystack-signature');
        $secret = config('services.paystack.secret_key');
        $computedHash = hash_hmac('sha512', $request->getContent(), $secret);

        if ($signature !== $computedHash) {
            Log::warning('Paystack webhook signature mismatch');
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $payload = $request->all();
        $event = $payload['event'] ?? '';        
        // Handle Subscription updates
        if ($event === 'invoice.payment_succeeded') {
            $metadata = $payload['data']['metadata'] ?? [];
            if (isset($metadata['is_b2b_subscription'])) {
                // E.g. finding user/organization by custom metadata
                $user = \App\Models\User::find($metadata['user_id']);
                $plan = \App\Models\Plan::find($metadata['plan_id']);
                
                if ($user && $plan) {
                    $user->subscribeTo(
                        $plan, 
                        'paystack', 
                        $payload['data']['subscription'] ?? null
                    );
                    
                    // Mark CRM Deal as Won possibly
                    if (isset($metadata['deal_id'])) {
                        $deal = \App\Models\CrmDeal::find($metadata['deal_id']);
                        if ($deal) {
                            $deal->update([
                                'stage' => 'Won',
                                'status' => 'won'
                            ]);
                            \App\Models\CrmDealStageHistory::create([
                                'deal_id' => $deal->id,
                                'new_stage' => 'Won',
                                'moved_by_user_id' => $user->id,
                            ]);
                        }
                    }
                }
                return response()->json(['status' => 'ok']);
            }
        }
        if ($event === 'charge.success') {
            $success = $this->paymentService->verifyPaystackWebhook($payload);
            if ($success) {
                $this->sendPaymentNotifications($payload['data']['reference'] ?? null);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Flutterwave webhook handler.
     */
    public function flutterwave(Request $request)
    {
        // Verify hash
        $secretHash = config('services.flutterwave.secret_hash');
        $signature = $request->header('verif-hash');

        if ($secretHash && $signature !== $secretHash) {
            Log::warning('Flutterwave webhook hash mismatch');
            return response()->json(['error' => 'Invalid hash'], 400);
        }

        $payload = $request->all();
        $event = $payload['event'] ?? '';

        if ($event === 'charge.completed' && ($payload['data']['status'] ?? '') === 'successful') {
            $success = $this->paymentService->verifyFlutterwaveWebhook($payload);
            if ($success) {
                $this->sendPaymentNotifications(null, $payload['data']['tx_ref'] ?? null);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Stripe webhook handler.
     */
    public function stripe(Request $request)
    {
        $signature = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');

        // Basic signature verification
        if ($endpointSecret && $signature) {
            $elements = collect(explode(',', $signature))
                ->mapWithKeys(function ($part) {
                    [$key, $value] = explode('=', $part, 2);
                    return [$key => $value];
                });

            $timestamp = $elements->get('t');
            $expectedSig = $elements->get('v1');
            $payload = $timestamp . '.' . $request->getContent();
            $computedSig = hash_hmac('sha256', $payload, $endpointSecret);

            if (!hash_equals($computedSig, $expectedSig ?? '')) {
                Log::warning('Stripe webhook signature mismatch');
                return response()->json(['error' => 'Invalid signature'], 400);
            }
        }

        $payload = $request->all();

        if (($payload['type'] ?? '') === 'checkout.session.completed') {
            $this->paymentService->verifyStripeWebhook($payload, $signature ?? '');
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Send payment notifications after successful payment.
     */
    private function sendPaymentNotifications(?string $reference, ?string $txRef = null): void
    {
        try {
            $payment = \App\Models\Payment::query()
                ->when($reference, fn($q) => $q->where('gateway_reference', $reference))
                ->when($txRef, fn($q) => $q->where('gateway_reference', $txRef))
                ->with(['order.items.product', 'user'])
                ->first();

            if ($payment && $payment->user) {
                $this->notificationService->sendPaymentReceipt(
                    $payment->user,
                    $payment->order->uuid,
                    $payment->amount,
                    $payment->currency,
                );
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send payment notification', ['error' => $e->getMessage()]);
        }
    }
}
