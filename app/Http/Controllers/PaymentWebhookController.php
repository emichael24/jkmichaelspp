<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\NotificationService;
use App\Services\PaymentRefundService;
use App\Services\PaymentService;
use App\Services\SubscriptionBillingService;
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
        $mode = app(\App\Services\PaystackSettings::class)->signedMode($request->getContent(), $request->header('x-paystack-signature'));
        if (! $mode) return response()->json(['error' => 'Invalid signature'], 400);

        $payload = $request->all();
        $event = $payload['event'] ?? '';
        if (str_starts_with($event, 'refund.')) {
            $payment = Payment::where('gateway', 'paystack')->where('gateway_reference', data_get($payload, 'data.transaction_reference'))->first();
            if ($payment?->refund && (! $payment->payment_mode || $payment->payment_mode === $mode)) {
                app(PaymentRefundService::class)->reconcile($payment->refund);
            }

            return response()->json(['status' => 'ok']);
        }
        $billing = app(SubscriptionBillingService::class);
        if (in_array($event, ['subscription.create', 'subscription.disable', 'subscription.not_renew', 'invoice.update', 'invoice.payment_failed'], true)) {
            $billing->webhook($payload, $mode);
        }
        if ($event === 'charge.success' && $billing->confirm($payload['data']['reference'] ?? '', null, $mode)) {
            return response()->json(['status' => 'ok']);
        }
        if ($event === 'charge.success') {
            $success = $this->paymentService->verifyPaystackWebhook($payload, $mode);
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

        if (! is_string($secretHash) || $secretHash === '' || ! is_string($signature) || ! hash_equals($secretHash, $signature)) {
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

        if (! is_string($endpointSecret) || $endpointSecret === '' || ! is_string($signature)) {
            return response()->json(['error' => 'Webhook verification is unavailable'], 400);
        }
        $elements = [];
        foreach (explode(',', $signature) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) === 2) {
                $elements[$pair[0]][] = $pair[1];
            }
        }
        $timestamp = $elements['t'][0] ?? '';
        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300) {
            return response()->json(['error' => 'Invalid signature timestamp'], 400);
        }
        $computedSig = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $endpointSecret);
        if (! collect($elements['v1'] ?? [])->contains(fn ($value) => hash_equals($computedSig, $value))) {
            return response()->json(['error' => 'Invalid signature'], 400);
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
            $payment = Payment::query()
                ->when($reference, fn ($q) => $q->where('gateway_reference', $reference))
                ->when($txRef, fn ($q) => $q->where('gateway_reference', $txRef))
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
