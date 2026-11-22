<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class PaymentRefundService
{
    public function request(Payment $payment, User $actor, string $reason): PaymentRefund
    {
        abort_unless($actor->hasRole('platform_admin'), 403);
        app(LearningAccessService::class)->assertActiveAccount($actor);
        abort_unless(filled(app(PaystackSettings::class)->secret($payment->payment_mode)), 422, 'Paystack is not configured.');
        $refund = DB::transaction(function () use ($payment, $actor, $reason) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $existing = PaymentRefund::where('payment_id', $payment->id)->first();
            if ($existing) {
                return $existing;
            }
            abort_unless($payment->gateway === 'paystack' && $payment->status === 'success' && $payment->amount > 0, 422, 'Only a verified, positive Paystack payment can be refunded here.');

            return PaymentRefund::create(['payment_id' => $payment->id, 'requested_by' => $actor->id, 'reason' => $reason,
                'amount_minor' => (int) round($payment->amount * 100), 'currency' => $payment->currency, 'status' => 'requested']);
        }, 3);

        // Commit intent before contacting the provider. Never blindly repeat an uncertain POST.
        $result = Cache::lock('payment-refund:'.$refund->id, 60)->get(function () use ($refund) {
            $refund->refresh();
            if ($refund->status !== 'requested') {
                return $refund;
            }
            $refund->update(['status' => 'unknown', 'submitted_at' => now()]);
            try {
                $response = Http::withToken(app(PaystackSettings::class)->secret($refund->payment->payment_mode))->connectTimeout(5)->timeout(20)
                    ->post('https://api.paystack.co/refund', ['transaction' => $refund->payment->gateway_reference,
                        'amount' => $refund->amount_minor, 'currency' => $refund->currency, 'merchant_note' => 'JK Michaels refund '.$refund->id]);
                if ($response->successful() && $response->json('status') && is_array($response->json('data'))) {
                    $this->applyReceipt($refund, $response->json('data'));
                }
            } catch (ConnectionException $error) {
                report($error);
            }

            return $refund->fresh();
        });

        return $result === false ? $refund->fresh() : $result;
    }

    public function reconcile(PaymentRefund $refund): PaymentRefund
    {
        if ($refund->status === 'processed') {
            return $refund;
        }
        try {
            $transactionId = null;
            $client = Http::withToken(app(PaystackSettings::class)->secret($refund->payment->payment_mode))->connectTimeout(5)->timeout(20);
            if ($refund->provider_refund_id) {
                $response = $client->get('https://api.paystack.co/refund/'.rawurlencode($refund->provider_refund_id));
                $data = $response->json('data');
            } else {
                $transaction = app(PaystackService::class)->verifyTransaction($refund->payment->gateway_reference, $refund->payment->payment_mode);
                abort_unless(($transaction['reference'] ?? null) === $refund->payment->gateway_reference && isset($transaction['id']), 422);
                $response = $client->get('https://api.paystack.co/refund', ['transaction' => $transaction['id'], 'perPage' => 100]);
                $transactionId = $transaction['id'];
                $matches = collect($response->json('data', []))->filter(fn ($item) => $this->matches($refund, $item, $transaction['id']));
                $data = $matches->count() === 1 ? $matches->first() : null;
            }
            if (! $response->successful() || ! $response->json('status') || ! is_array($data)) {
                throw ValidationException::withMessages(['refund' => 'Refund settlement could not be confirmed. Review the existing transaction in Paystack; do not initiate another refund.']);
            }
            $this->applyReceipt($refund, $data, $transactionId);
        } catch (ConnectionException $error) {
            report($error);
            throw ValidationException::withMessages(['refund' => 'The provider is unreachable. The existing refund request is retained.']);
        }

        return $refund->fresh();
    }

    private function matches(PaymentRefund $refund, array $data, mixed $transactionId = null): bool
    {
        $transaction = $data['transaction'] ?? null;
        $identityMatches = is_array($transaction) ? ($transaction['reference'] ?? null) === $refund->payment->gateway_reference
            : (isset($transactionId) && (string) $transaction === (string) $transactionId)
                || (isset($refund->payment->gateway_response['id']) && (string) $transaction === (string) $refund->payment->gateway_response['id']);

        return $identityMatches && isset($data['id'], $data['amount']) && is_numeric($data['amount'])
            && (int) $data['amount'] === $refund->amount_minor && ($data['currency'] ?? null) === $refund->currency
            && (! $refund->provider_refund_id || (string) $data['id'] === $refund->provider_refund_id);
    }

    private function applyReceipt(PaymentRefund $refund, array $data, mixed $transactionId = null): void
    {
        abort_unless($this->matches($refund, $data, $transactionId), 422, 'Provider refund details do not match the requested payment.');
        abort_unless(in_array($data['status'] ?? null, ['pending', 'processing', 'needs-attention', 'failed', 'processed'], true), 422, 'Unrecognized provider refund status.');
        DB::transaction(function () use ($refund, $data) {
            // Same lock order as payment settlement.
            $payment = Payment::whereKey($refund->payment_id)->lockForUpdate()->firstOrFail();
            $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            $current = PaymentRefund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($current->status === 'processed') {
                return;
            }
            $current->update(['provider_refund_id' => (string) $data['id'], 'status' => $data['status'], 'reconciled_at' => now(),
                'processed_at' => $data['status'] === 'processed' ? now() : null,
                'receipt' => collect($data)->only(['id', 'status', 'amount', 'currency', 'domain', 'refunded_at', 'expected_at'])->all()]);
            if ($current->status === 'processed') {
                $payment->markRefunded();
                $order->update(['status' => 'refunded']);
            }
        }, 3);
    }
}
