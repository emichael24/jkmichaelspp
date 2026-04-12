<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaystackTransaction;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
            'order_id'   => 'required|exists:orders,id',
            'amount'     => 'required|numeric|min:100',
            'email'      => 'nullable|email',
        ]);

        $user    = auth()->user();
        $orderId = $request->input('order_id');
        $amount  = $request->input('amount');
        $email   = $request->input('email', $user->email);

        // Prevent double-payment
        $existing = PaystackTransaction::where('order_id', $orderId)
            ->whereIn('status', ['success', 'pending'])
            ->first();

        if ($existing && $existing->status === 'success') {
            return response()->json(['error' => 'This order has already been paid.'], 422);
        }

        try {
            $result = $this->paystack->initializeTransaction([
                'email'    => $email,
                'amount'   => $amount,
                'metadata' => [
                    'order_id'   => $orderId,
                    'user_id'    => $user->id,
                    'user_name'  => $user->first_name . ' ' . $user->last_name,
                    'platform'   => 'jkmichaels',
                ],
            ]);

            // Record the transaction
            PaystackTransaction::create([
                'order_id'          => $orderId,
                'user_id'           => $user->id,
                'reference'         => $result['reference'],
                'access_code'       => $result['access_code'],
                'authorization_url' => $result['authorization_url'],
                'amount'            => $amount,
                'currency'          => 'NGN',
                'status'            => 'initiated',
                'ip_address'        => $request->ip(),
                'metadata'          => ['order_id' => $orderId],
            ]);

            return response()->json([
                'reference'         => $result['reference'],
                'authorization_url' => $result['authorization_url'],
                'access_code'       => $result['access_code'],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
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

        $transaction = PaystackTransaction::where('reference', $reference)->first();

        if (! $transaction) {
            return redirect('/orders')->with('error', 'Transaction not found.');
        }

        // Avoid re-processing
        if ($transaction->status === 'success') {
            return redirect("/orders/{$transaction->order->uuid}")->with('success', 'Payment already confirmed.');
        }

        try {
            $data = $this->paystack->verifyTransaction($reference);

            $isPaid = $data['status'] === 'success';

            $transaction->update([
                'status'           => $isPaid ? 'success' : 'failed',
                'channel'          => $data['channel'] ?? null,
                'gateway_response' => $data,
                'paid_at'          => $isPaid ? now() : null,
            ]);

            if ($isPaid && $transaction->order_id) {
                $order = Order::find($transaction->order_id);
                if ($order) {
                    $order->update(['status' => 'paid', 'paid_at' => now()]);

                    // Trigger enrollment for purchased items
                    app(\App\Services\EnrollmentService::class)->processOrder($order);
                }
            }

            if ($isPaid) {
                return redirect("/orders/{$transaction->order->uuid}")
                    ->with('success', 'Payment successful! You can now access your course.');
            }

            return redirect('/orders')->with('error', 'Payment was not successful. Please try again.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Paystack callback error: ' . $e->getMessage());
            return redirect('/orders')->with('error', 'Could not verify payment. Contact support if charged.');
        }
    }
}
