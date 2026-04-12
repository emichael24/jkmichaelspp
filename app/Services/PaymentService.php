<?php

namespace App\Services;

use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\CourseEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentService
{
    // ─────────────────────────────────────────────
    // Order Creation
    // ─────────────────────────────────────────────

    public function createOrder(User $user, array $items, ?string $discountCodeStr = null, string $gateway = 'paystack'): array
    {
        return DB::transaction(function () use ($user, $items, $discountCodeStr, $gateway) {
            $subtotal = 0;
            $orderItems = [];
            $currency = 'NGN';

            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $qty = $item['quantity'] ?? 1;
                $unitPrice = $product->getCurrentPrice();
                $currency = $product->currency;

                $orderItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'total_price' => $unitPrice * $qty,
                ];
                $subtotal += $unitPrice * $qty;
            }

            // Apply discount
            $discountAmount = 0;
            $discountCode = null;
            if ($discountCodeStr) {
                $discountCode = DiscountCode::where('code', $discountCodeStr)->first();
                if ($discountCode && $discountCode->isValid()) {
                    $discountAmount = $discountCode->calculateDiscount($subtotal);
                }
            }

            $total = max(0, $subtotal - $discountAmount);

            // Create order
            $order = Order::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'organization_id' => $user->organization_id,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'total' => $total,
                'currency' => $currency,
                'discount_code_id' => $discountCode?->id,
                'status' => 'pending',
            ]);

            // Create order items
            foreach ($orderItems as $oi) {
                OrderItem::create(array_merge($oi, ['order_id' => $order->id]));
            }

            // Increment discount usage
            $discountCode?->incrementUsage();

            // Create payment + initiate gateway checkout
            $reference = 'JKM-' . strtoupper(Str::random(12));
            $payment = Payment::create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'gateway' => $gateway,
                'gateway_reference' => $reference,
                'amount' => $total,
                'currency' => $currency,
                'status' => 'pending',
            ]);

            $checkoutData = $this->initiateGatewayCheckout($gateway, $payment, $user);

            return [
                'order' => $order->load('items.product'),
                'payment' => $payment,
                'checkout' => $checkoutData,
            ];
        });
    }

    // ─────────────────────────────────────────────
    // Gateway Initialization
    // ─────────────────────────────────────────────

    private function initiateGatewayCheckout(string $gateway, Payment $payment, User $user): array
    {
        return match ($gateway) {
            'paystack' => $this->initiatePaystack($payment, $user),
            'flutterwave' => $this->initiateFlutterwave($payment, $user),
            'stripe' => $this->initiateStripe($payment, $user),
            default => throw new \InvalidArgumentException("Unsupported gateway: {$gateway}"),
        };
    }

    private function initiatePaystack(Payment $payment, User $user): array
    {
        $secretKey = config('services.paystack.secret_key');

        $response = Http::withToken($secretKey)
            ->post('https://api.paystack.co/transaction/initialize', [
                'email' => $user->email,
                'amount' => (int) ($payment->amount * 100), // kobo
                'reference' => $payment->gateway_reference,
                'currency' => $payment->currency,
                'callback_url' => url('/orders/' . $payment->order->uuid . '?gateway=paystack'),
                'metadata' => [
                    'order_id' => $payment->order_id,
                    'user_id' => $user->id,
                ],
            ]);

        if ($response->successful() && $response->json('status')) {
            return [
                'gateway' => 'paystack',
                'authorization_url' => $response->json('data.authorization_url'),
                'reference' => $payment->gateway_reference,
            ];
        }

        Log::error('Paystack init failed', ['response' => $response->json()]);
        return ['gateway' => 'paystack', 'authorization_url' => null, 'reference' => $payment->gateway_reference, 'error' => 'Failed to initialize payment'];
    }

    private function initiateFlutterwave(Payment $payment, User $user): array
    {
        $secretKey = config('services.flutterwave.secret_key');

        $response = Http::withToken($secretKey)
            ->post('https://api.flutterwave.com/v3/payments', [
                'tx_ref' => $payment->gateway_reference,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'redirect_url' => url('/orders/' . $payment->order->uuid . '?gateway=flutterwave'),
                'customer' => [
                    'email' => $user->email,
                    'name' => $user->first_name . ' ' . $user->last_name,
                ],
                'meta' => [
                    'order_id' => $payment->order_id,
                ],
            ]);

        if ($response->successful() && $response->json('status') === 'success') {
            return [
                'gateway' => 'flutterwave',
                'authorization_url' => $response->json('data.link'),
                'reference' => $payment->gateway_reference,
            ];
        }

        Log::error('Flutterwave init failed', ['response' => $response->json()]);
        return ['gateway' => 'flutterwave', 'authorization_url' => null, 'reference' => $payment->gateway_reference, 'error' => 'Failed to initialize payment'];
    }

    private function initiateStripe(Payment $payment, User $user): array
    {
        $secretKey = config('services.stripe.secret');

        $response = Http::withBasicAuth($secretKey, '')
            ->asForm()
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'payment_method_types[]' => 'card',
                'line_items[0][price_data][currency]' => strtolower($payment->currency),
                'line_items[0][price_data][product_data][name]' => 'Order #' . $payment->order->uuid,
                'line_items[0][price_data][unit_amount]' => (int) ($payment->amount * 100),
                'line_items[0][quantity]' => 1,
                'mode' => 'payment',
                'success_url' => url('/orders/' . $payment->order->uuid . '?gateway=stripe&session_id={CHECKOUT_SESSION_ID}'),
                'cancel_url' => url('/orders/' . $payment->order->uuid . '?cancelled=true'),
                'client_reference_id' => $payment->gateway_reference,
                'customer_email' => $user->email,
            ]);

        if ($response->successful()) {
            $payment->update(['gateway_reference' => $response->json('id')]);
            return [
                'gateway' => 'stripe',
                'authorization_url' => $response->json('url'),
                'reference' => $response->json('id'),
            ];
        }

        Log::error('Stripe init failed', ['response' => $response->json()]);
        return ['gateway' => 'stripe', 'authorization_url' => null, 'reference' => $payment->gateway_reference, 'error' => 'Failed to initialize payment'];
    }

    // ─────────────────────────────────────────────
    // Webhook Verification
    // ─────────────────────────────────────────────

    public function verifyPaystackWebhook(array $payload): bool
    {
        $reference = $payload['data']['reference'] ?? null;
        if (!$reference) return false;

        $payment = Payment::where('gateway_reference', $reference)->where('gateway', 'paystack')->first();
        if (!$payment || $payment->isSuccessful()) return false;

        // Verify with Paystack API
        $secretKey = config('services.paystack.secret_key');
        $response = Http::withToken($secretKey)
            ->get("https://api.paystack.co/transaction/verify/{$reference}");

        if ($response->successful() && $response->json('data.status') === 'success') {
            return $this->completePayment($payment, $response->json('data'));
        }

        $payment->markFailed($response->json('data') ?? []);
        $payment->order->markFailed();
        return false;
    }

    public function verifyFlutterwaveWebhook(array $payload): bool
    {
        $transactionId = $payload['data']['id'] ?? null;
        $txRef = $payload['data']['tx_ref'] ?? null;
        if (!$txRef) return false;

        $payment = Payment::where('gateway_reference', $txRef)->where('gateway', 'flutterwave')->first();
        if (!$payment || $payment->isSuccessful()) return false;

        // Verify with Flutterwave API
        $secretKey = config('services.flutterwave.secret_key');
        $response = Http::withToken($secretKey)
            ->get("https://api.flutterwave.com/v3/transactions/{$transactionId}/verify");

        if ($response->successful() && $response->json('data.status') === 'successful') {
            return $this->completePayment($payment, $response->json('data'));
        }

        $payment->markFailed($response->json('data') ?? []);
        $payment->order->markFailed();
        return false;
    }

    public function verifyStripeWebhook(array $payload, string $signature): bool
    {
        $sessionId = $payload['data']['object']['id'] ?? null;
        $clientRef = $payload['data']['object']['client_reference_id'] ?? null;

        $payment = Payment::where('gateway_reference', $sessionId)
            ->orWhere('gateway_reference', $clientRef)
            ->where('gateway', 'stripe')
            ->first();

        if (!$payment || $payment->isSuccessful()) return false;

        if (($payload['type'] ?? '') === 'checkout.session.completed') {
            return $this->completePayment($payment, $payload['data']['object']);
        }

        return false;
    }

    // ─────────────────────────────────────────────
    // Payment Completion + Auto-Enrollment
    // ─────────────────────────────────────────────

    private function completePayment(Payment $payment, array $gatewayData): bool
    {
        return DB::transaction(function () use ($payment, $gatewayData) {
            $payment->markSuccessful($gatewayData);
            $payment->order->markPaid();

            // Auto-enroll user in purchased courses
            $this->autoEnroll($payment->order);

            return true;
        });
    }

    private function autoEnroll(Order $order): void
    {
        $order->load('items.product');

        foreach ($order->items as $item) {
            $product = $item->product;
            if (!$product) continue;

            $productable = $product->productable;
            if (!$productable) continue;

            if ($product->productable_type === 'App\\Models\\Course') {
                // Enroll in course
                CourseEnrollment::firstOrCreate([
                    'user_id' => $order->user_id,
                    'course_id' => $productable->id,
                ], [
                    'enrolled_at' => now(),
                    'status' => 'active',
                ]);
            } elseif ($product->productable_type === 'App\\Models\\Bundle') {
                // Enroll in all courses in bundle
                $courses = $productable->courses;
                foreach ($courses as $course) {
                    CourseEnrollment::firstOrCreate([
                        'user_id' => $order->user_id,
                        'course_id' => $course->id,
                    ], [
                        'enrolled_at' => now(),
                        'status' => 'active',
                    ]);
                }
            }
        }
    }

    // ─────────────────────────────────────────────
    // Discount Validation
    // ─────────────────────────────────────────────

    public function validateDiscount(string $code, ?int $productId = null): array
    {
        $discount = DiscountCode::where('code', $code)->first();

        if (!$discount) {
            return ['valid' => false, 'message' => 'Invalid discount code.'];
        }

        if (!$discount->isValid($productId)) {
            if ($discount->max_uses && $discount->used_count >= $discount->max_uses) {
                return ['valid' => false, 'message' => 'This code has reached its maximum uses.'];
            }
            if ($discount->valid_until && $discount->valid_until->isPast()) {
                return ['valid' => false, 'message' => 'This code has expired.'];
            }
            return ['valid' => false, 'message' => 'This code is not valid for this product.'];
        }

        return [
            'valid' => true,
            'type' => $discount->type,
            'value' => $discount->value,
            'message' => $discount->type === 'percentage'
                ? "{$discount->value}% discount applied"
                : "₦{$discount->value} discount applied",
        ];
    }
}
