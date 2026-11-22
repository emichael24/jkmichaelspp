<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaystackTransaction;
use App\Services\PaymentService;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function __construct(protected PaystackService $paystack) {}

    /**
     * Initialize a Paystack transaction for an order.
     * Called from checkout page — returns authorization_url for redirect.
     */
    public function initializePaystack(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'amount' => 'sometimes|numeric|min:0',
            'email' => 'nullable|email',
        ]);

        $user = auth()->user();
        $order = Order::whereKey($request->input('order_id'))->where('user_id', $user->id)->firstOrFail();
        abort_unless($order->status === 'pending', 422, 'This order is not awaiting payment.');
        // Both checkout entry points use the same payment and reference.
        abort_unless($order->payments()->where('gateway', 'paystack')->where('status', 'pending')->exists(), 409, 'This order has no recoverable Paystack checkout. Contact billing support with the order reference.');
        $checkout = app(PaymentService::class)->resumeCheckout($order, $user);

        return response()->json($checkout, isset($checkout['error']) ? 422 : 200);
    }

    /**
     * Handle Paystack redirect callback after payment.
     */
    public function paystackCallback(Request $request)
    {
        $reference = $request->get('reference') ?? $request->get('trxref');

        if (! $reference) {
            return redirect('/orders')->with('error', 'Invalid payment reference.');
        }

        $payment = Payment::where('gateway', 'paystack')->where('gateway_reference', $reference)->where('user_id', $request->user()->id)->first();
        if ($payment) {
            $confirmed = app(PaymentService::class)->refreshOrder($payment->order, $request->user());

            return redirect()->route('orders.show', $payment->order->uuid)->with($confirmed ? 'success' : 'info', $confirmed ? 'Payment verified. Your order is complete.' : 'Payment is not yet confirmed. Check its status before paying again.');
        }

        $transaction = PaystackTransaction::where('reference', $reference)->where('user_id', $request->user()->id)->first();

        if (! $transaction) {
            return redirect('/orders')->with('error', 'Transaction not found.');
        }

        // Avoid re-processing
        if ($transaction->status === 'success') {
            return redirect("/orders/{$transaction->order->uuid}")->with('success', 'Payment already confirmed.');
        }

        try {
            $data = $this->paystack->verifyTransaction($reference, $transaction->payment_mode);

            $isPaid = ($data['status'] ?? '') === 'success'
                && ($data['reference'] ?? '') === $reference
                && strtoupper($data['currency'] ?? '') === strtoupper($transaction->currency)
                && isset($data['amount']) && (int) $data['amount'] === (int) round((float) $transaction->amount * 100);

            $isPaid = DB::transaction(function () use ($transaction, $data, $isPaid) {
                $transaction = PaystackTransaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();
                if ($transaction->status === 'success') {
                    return true;
                }
                $order = Order::whereKey($transaction->order_id)->lockForUpdate()->firstOrFail();
                $transaction->update([
                    'status' => $isPaid ? 'success' : (($data['reference'] ?? '') === $transaction->reference && in_array($data['status'] ?? '', ['failed', 'abandoned', 'reversed'], true) ? 'failed' : $transaction->status),
                    'channel' => $data['channel'] ?? null,
                    'gateway_response' => collect($data)->only(['id', 'reference', 'status', 'amount', 'currency', 'paid_at', 'domain'])->all(),
                    'paid_at' => $isPaid ? now() : null,
                ]);
                if ($isPaid && ! in_array($order->status, ['paid', 'refunded'], true)) {
                    $order->markPaid();
                    app(PaymentService::class)->fulfillOrder($order);
                }

                return $isPaid;
            });

            if ($isPaid) {
                return redirect("/orders/{$transaction->order->uuid}")
                    ->with('success', 'Payment successful! You can now access your course.');
            }

            return redirect('/orders')->with('warning', 'Payment is not confirmed. Contact billing support if charged before starting another checkout.');
        } catch (\Throwable $e) {
            report($e);

            return redirect('/orders')->with('error', 'Could not verify payment. Contact support if charged.');
        }
    }
}
