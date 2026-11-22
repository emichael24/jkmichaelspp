<?php

namespace App\Services;

use App\Models\Bundle;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    // ─────────────────────────────────────────────
    // Order Creation
    // ─────────────────────────────────────────────

    public function createOrder(User $user, array $items, ?string $discountCodeStr = null, string $gateway = 'paystack', bool $forOrganization = false): array
    {
        abort_unless(in_array($gateway, array_column($this->availableGateways(), 'id'), true), 422, 'This payment provider is not configured.');
        app(LearningAccessService::class)->assertActiveAccount($user);
        $organization = $forOrganization ? app(OrganizationCourseService::class)->organization($user) : null;
        abort_unless(count($items) >= 1 && count($items) <= 50, 422);
        $result = DB::transaction(function () use ($user, $items, $discountCodeStr, $gateway, $organization) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $subtotal = 0;
            $orderItems = [];
            $currency = null;

            foreach ($items as $item) {
                $product = Product::purchasable()->find($item['product_id']);
                abort_unless($product, 422, 'This product is unavailable.');
                if ($product->productable instanceof Course) {
                    abort_unless($product->productable->is_published, 422, 'This course is unavailable.');
                    abort_if($product->productable->superseded_at, 422, 'Choose the current course version.');
                    if (! $organization) app(CoursePrerequisiteService::class)->assertSatisfied($product->productable, $user);
                }
                abort_if($organization && ! ($product->productable instanceof Course), 422, 'Choose individual courses for organization licenses.');
                if ($product->productable instanceof Bundle) {
                    foreach ($product->productable->courses as $course) {
                        app(CoursePrerequisiteService::class)->assertSatisfied($course, $user);
                    }
                }
                $qty = (int) ($item['quantity'] ?? 1);
                abort_unless($organization ? $qty >= 1 && $qty <= 10000 : $qty === 1, 422, 'Choose 1–10,000 places for a company course, or one place for individual checkout.');
                $unitPrice = $product->getCurrentPrice();
                abort_if($unitPrice < 0, 422, 'This product has invalid pricing.');
                abort_if($currency !== null && $currency !== $product->currency, 422, 'Use separate orders for different currencies.');
                $currency = $product->currency;

                $orderItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'total_price' => $unitPrice * $qty,
                    'licensed_course_id' => $organization ? $product->productable_id : null,
                    'product_name' => $product->name,
                ];
                $subtotal += $unitPrice * $qty;
            }

            // User row lock serializes duplicate submissions before any coupon allocation or provider call.
            $cart = collect($orderItems)->sortBy('product_id')->map(fn ($item) => [$item['product_id'], $item['quantity'], (int) round($item['unit_price'] * 100)])->values()->all();
            abort_if($subtotal > 99999999.99, 422, 'Split this purchase into smaller orders.');
            foreach (Order::where('user_id', $user->id)->where('purchase_scope', $organization ? 'organization' : 'individual')
                ->when($organization, fn ($q) => $q->where('organization_id', $organization->id))
                ->where('status', 'pending')->where('currency', $currency)->where('created_at', '>=', now()->subDay())
                ->with(['items', 'payments', 'discountCode'])->latest('id')->limit(20)->get() as $pending) {
                $payment = $pending->payments->sortByDesc('id')->first();
                if ($gateway === 'paystack' && $payment?->payment_mode && $payment->payment_mode !== app(PaystackSettings::class)->mode()) continue;
                $existingCart = $pending->items->sortBy('product_id')->map(fn ($item) => [$item->product_id, $item->quantity, (int) round($item->unit_price * 100)])->values()->all();
                if ($payment?->status === 'pending' && $payment->gateway === $gateway && $existingCart === $cart && ($pending->discountCode?->code ?? '') === ($discountCodeStr ?? '')) {
                    return ['order' => $pending->load('items.product'), 'payment' => $payment];
                }
            }

            // Apply discount
            $discountAmount = 0;
            $discountCode = null;
            if ($discountCodeStr) {
                $discountCode = DiscountCode::where('code', $discountCodeStr)->lockForUpdate()->first();
                abort_unless($discountCode && $discountCode->isValid($discountCode->product_id), 422, 'This discount is invalid, expired or fully allocated.');
                $eligibleSubtotal = collect($orderItems)->filter(fn ($item) => ! $discountCode->product_id || $item['product_id'] === $discountCode->product_id)->sum('total_price');
                abort_unless($eligibleSubtotal > 0, 422, 'This discount does not apply to the products in your order.');
                $discountAmount = $discountCode->calculateDiscount($eligibleSubtotal);
            }

            $total = max(0, $subtotal - $discountAmount);

            // Create order
            $order = Order::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'organization_id' => $user->organization_id,
                'purchase_scope' => $organization ? 'organization' : 'individual',
                'billing_details' => $organization ? [...$organization->only(['name', 'contact_email', 'contact_phone', 'address', 'country']),
                    'payment_mode' => $gateway === 'paystack' && app(PaystackSettings::class)->mode() === 'test' ? 'test' : 'live'] : null,
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
            $reference = 'JKM-'.strtoupper(Str::random(12));
            $payment = Payment::create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'gateway' => $gateway,
                'payment_mode' => $gateway === 'paystack' ? app(PaystackSettings::class)->mode() : null,
                'gateway_reference' => $reference,
                'amount' => $total,
                'currency' => $currency,
                'status' => 'pending',
            ]);

            return [
                'order' => $order->load('items.product'),
                'payment' => $payment,
            ];
        }, 3);
        $result['checkout'] = $this->resumeCheckout($result['order'], $user);

        return $result;
    }

    public function resumeCheckout(Order $order, User $user): array
    {
        abort_unless($order->accessibleBy($user), 404);
        app(LearningAccessService::class)->assertActiveAccount($user);
        abort_unless($order->fresh()->status === 'pending', 409, 'This order is not awaiting payment.');
        $result = Cache::lock('order-checkout:'.$order->id, 60)->get(function () use ($order, $user) {
            $payment = $order->payments()->latest('id')->firstOrFail();
            abort_unless($payment->status === 'pending', 409, 'This payment needs reconciliation before another checkout.');
            if ((float) $payment->amount === 0.0) {
                $this->completePayment($payment, ['status' => 'success', 'reason' => 'zero_value_order']);

                return ['completed' => true];
            }
            abort_if($payment->gateway === 'paystack' && $payment->payment_mode && $payment->payment_mode !== app(PaystackSettings::class)->mode(), 409, 'This checkout belongs to a previous payment mode. Check its payment status, or start a new order from the store.');
            $existing = data_get($payment->gateway_response, 'checkout.authorization_url');
            if ($existing) {
                return ['authorization_url' => $existing, 'reference' => $payment->gateway_reference, 'gateway' => $payment->gateway];
            }
            try {
                $checkout = $this->initiateGatewayCheckout($payment->gateway, $payment, $user);
            } catch (ConnectionException $error) {
                report($error);

                return ['error' => 'The provider did not confirm checkout initialization. Your order and reference are retained; check payment status before retrying.'];
            }
            if (! empty($checkout['authorization_url'])) {
                // Do not retain provider authorization/customer/card details in an initialization response.
                DB::transaction(function () use ($payment, $checkout) {
                    $current = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
                    if ($current->status === 'pending') {
                        $current->update(['gateway_response' => ['checkout' => $checkout]]);
                    }
                }, 3);
            }

            if ($payment->fresh()->isSuccessful()) {
                return ['completed' => true];
            }

            return $checkout;
        });
        abort_if($result === false, 409, 'A checkout is already being prepared. Wait a moment and try again.');

        return $result;
    }

    public function refreshOrder(Order $order, User $user): bool
    {
        abort_unless($order->accessibleBy($user), 404);
        app(LearningAccessService::class)->assertActiveAccount($user);
        if ($order->fresh()->status === 'paid') {
            return true;
        }
        $payment = $order->payments()->where('gateway', 'paystack')->where('status', 'pending')->latest('id')->first();
        abort_unless($payment, 409, 'No pending Paystack payment is available to check.');
        try {
            $this->verifyPaystackWebhook(['data' => ['reference' => $payment->gateway_reference]]);
        } catch (ConnectionException $error) {
            report($error);
            throw ValidationException::withMessages(['payment' => 'The provider is temporarily unreachable. Your order is unchanged. Check again before making another payment.']);
        }

        return $order->fresh()->status === 'paid';
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
        $secretKey = app(PaystackSettings::class)->secret($payment->payment_mode);

        $response = Http::withToken($secretKey)->connectTimeout(5)->timeout(20)
            ->post('https://api.paystack.co/transaction/initialize', [
                'email' => $user->email,
                'amount' => (int) round($payment->amount * 100), // kobo
                'reference' => $payment->gateway_reference,
                'currency' => $payment->currency,
                'callback_url' => url('/orders/'.$payment->order->uuid.'?gateway=paystack'),
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

        Log::error('Paystack init failed', ['status' => $response->status()]);

        return ['gateway' => 'paystack', 'authorization_url' => null, 'reference' => $payment->gateway_reference, 'error' => 'Failed to initialize payment'];
    }

    private function initiateFlutterwave(Payment $payment, User $user): array
    {
        $secretKey = config('services.flutterwave.secret_key');

        $response = Http::withToken($secretKey)->connectTimeout(5)->timeout(20)
            ->post('https://api.flutterwave.com/v3/payments', [
                'tx_ref' => $payment->gateway_reference,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'redirect_url' => url('/orders/'.$payment->order->uuid.'?gateway=flutterwave'),
                'customer' => [
                    'email' => $user->email,
                    'name' => $user->first_name.' '.$user->last_name,
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

        Log::error('Flutterwave init failed', ['status' => $response->status()]);

        return ['gateway' => 'flutterwave', 'authorization_url' => null, 'reference' => $payment->gateway_reference, 'error' => 'Failed to initialize payment'];
    }

    private function initiateStripe(Payment $payment, User $user): array
    {
        $secretKey = config('services.stripe.secret');

        $response = Http::withBasicAuth($secretKey, '')->connectTimeout(5)->timeout(20)
            ->asForm()
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'payment_method_types[]' => 'card',
                'line_items[0][price_data][currency]' => strtolower($payment->currency),
                'line_items[0][price_data][product_data][name]' => 'Order #'.$payment->order->uuid,
                'line_items[0][price_data][unit_amount]' => (int) round($payment->amount * 100),
                'line_items[0][quantity]' => 1,
                'mode' => 'payment',
                'success_url' => url('/orders/'.$payment->order->uuid.'?gateway=stripe&session_id={CHECKOUT_SESSION_ID}'),
                'cancel_url' => url('/orders/'.$payment->order->uuid.'?cancelled=true'),
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

        Log::error('Stripe init failed', ['status' => $response->status()]);

        return ['gateway' => 'stripe', 'authorization_url' => null, 'reference' => $payment->gateway_reference, 'error' => 'Failed to initialize payment'];
    }

    // ─────────────────────────────────────────────
    // Webhook Verification
    // ─────────────────────────────────────────────

    public function verifyPaystackWebhook(array $payload, ?string $signedMode = null): bool
    {
        $reference = $payload['data']['reference'] ?? null;
        if (! $reference) {
            return false;
        }

        $payment = Payment::where('gateway_reference', $reference)->where('gateway', 'paystack')->first();
        if (! $payment || $payment->isSuccessful() || $payment->status === 'refunded') {
            return false;
        }

        if ($signedMode && $payment->payment_mode && $signedMode !== $payment->payment_mode) return false;

        // Verify with Paystack API
        $secretKey = app(PaystackSettings::class)->secret($payment->payment_mode);
        $response = Http::withToken($secretKey)->connectTimeout(5)->timeout(20)
            ->get("https://api.paystack.co/transaction/verify/{$reference}");

        if ($response->json('data.domain') && $response->json('data.domain') !== ($payment->payment_mode ?: app(PaystackSettings::class)->mode())) {
            return false;
        }

        if ($response->successful() && $response->json('data.status') === 'success'
            && $response->json('data.reference') === $reference
            && $this->settlementMatches($payment, $response->json('data') ?? [], 'amount', 100)) {
            return $this->completePayment($payment, $response->json('data'));
        }

        // A timeout, provider outage or mismatched verification is not proof of failure.
        if ($response->successful() && $response->json('data.reference') === $reference && in_array($response->json('data.status'), ['failed', 'abandoned', 'reversed'], true)) {
            DB::transaction(function () use ($payment, $response) {
                $current = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
                $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
                if ($current->status === 'pending' && $order->status !== 'paid') {
                    $current->markFailed(['status' => $response->json('data.status')]);
                    $order->markFailed();
                }
            }, 3);
        }

        return false;
    }

    public function verifyFlutterwaveWebhook(array $payload): bool
    {
        $transactionId = $payload['data']['id'] ?? null;
        $txRef = $payload['data']['tx_ref'] ?? null;
        if (! $txRef) {
            return false;
        }

        $payment = Payment::where('gateway_reference', $txRef)->where('gateway', 'flutterwave')->first();
        if (! $payment || $payment->isSuccessful() || $payment->status === 'refunded') {
            return false;
        }

        // Verify with Flutterwave API
        $secretKey = config('services.flutterwave.secret_key');
        $response = Http::withToken($secretKey)->connectTimeout(5)->timeout(20)
            ->get("https://api.flutterwave.com/v3/transactions/{$transactionId}/verify");

        if ($response->successful() && $response->json('data.status') === 'successful'
            && $response->json('data.tx_ref') === $txRef
            && $this->settlementMatches($payment, $response->json('data') ?? [], 'amount', 1)) {
            return $this->completePayment($payment, $response->json('data'));
        }

        // A timeout, provider outage or mismatched verification is not proof of failure.
        if ($response->successful() && $response->json('data.tx_ref') === $txRef && in_array($response->json('data.status'), ['failed', 'abandoned', 'reversed'], true)) {
            DB::transaction(function () use ($payment, $response) {
                $current = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
                $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
                if ($current->status === 'pending' && $order->status !== 'paid') {
                    $current->markFailed(['status' => $response->json('data.status')]);
                    $order->markFailed();
                }
            }, 3);
        }

        return false;
    }

    public function verifyStripeWebhook(array $payload, string $signature): bool
    {
        $sessionId = $payload['data']['object']['id'] ?? null;
        $clientRef = $payload['data']['object']['client_reference_id'] ?? null;

        if (! $sessionId && ! $clientRef) {
            return false;
        }
        $payment = Payment::where('gateway', 'stripe')
            ->where(fn ($query) => $query->where('gateway_reference', $sessionId)->orWhere('gateway_reference', $clientRef))
            ->first();

        if (! $payment || $payment->isSuccessful() || $payment->status === 'refunded') {
            return false;
        }

        if (($payload['type'] ?? '') === 'checkout.session.completed'
            && ($payload['data']['object']['payment_status'] ?? '') === 'paid'
            && $this->settlementMatches($payment, $payload['data']['object'], 'amount_total', 100)) {
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
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->isSuccessful() || $payment->status === 'refunded') {
                return false;
            }
            $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            $payment->markSuccessful(collect($gatewayData)->only(['id', 'reference', 'status', 'amount', 'amount_total', 'currency', 'paid_at', 'domain', 'reason'])->all());
            if (in_array($order->status, ['paid', 'refunded'], true)) {
                return false;
            }
            $order->markPaid();

            // Auto-enroll user in purchased courses
            $this->fulfillOrder($order);

            return true;
        });
    }

    private function settlementMatches(Payment $payment, array $data, string $amountField, int $divisor): bool
    {
        return isset($data[$amountField], $data['currency']) && is_numeric($data[$amountField])
            && strtoupper($data['currency']) === strtoupper($payment->currency)
            && (int) round((float) $data[$amountField] * (100 / $divisor)) === (int) round((float) $payment->amount * 100);
    }

    public function fulfillOrder(Order $order): void
    {
        abort_unless($order->status === 'paid', 409, 'Only paid orders can grant access.');
        $order->load('items.product');

        if ($order->purchase_scope === 'organization') {
            foreach ($order->items as $item) {
                abort_unless($item->licensed_course_id && $order->organization_id, 409, 'This order needs a billing review.');
                \App\Models\OrganizationCourseLicense::firstOrCreate(['order_item_id' => $item->id],
                    ['organization_id' => $order->organization_id, 'course_id' => $item->licensed_course_id,
                        'quantity' => $item->quantity, 'granted_by' => $order->user_id, 'status' => 'active']);
            }
            return;
        }

        foreach ($order->items as $item) {
            $product = $item->product;
            if (! $product) {
                continue;
            }

            $productable = $product->productable;
            if (! $productable) {
                continue;
            }

            if ($product->productable_type === 'App\\Models\\Course') {
                // Enroll in course
                $this->grantCourse($order, $productable);
            } elseif ($product->productable_type === 'App\\Models\\Bundle') {
                // Enroll in all courses in bundle
                $courses = $productable->courses;
                foreach ($courses as $course) {
                    $this->grantCourse($order, $course);
                }
            }
        }
    }

    private function grantCourse(Order $order, Course $course): void
    {
        $enrollment = CourseEnrollment::firstOrCreate(['user_id' => $order->user_id, 'course_id' => $course->id],
            ['enrolled_at' => now(), 'status' => 'active', 'source_order_id' => $order->id]);
        // Repurchase restores a refunded grant, but never overrides an administrator suspension.
        if (in_array($enrollment->status, ['active', 'completed'], true)
            && ($enrollment->organization_course_license_id || ($enrollment->source_order_id && Order::whereKey($enrollment->source_order_id)->where('status', 'refunded')->exists()))) {
            $enrollment->update(['source_order_id' => $order->id, 'organization_course_license_id' => null]);
        }
    }

    // ─────────────────────────────────────────────
    // Discount Validation
    // ─────────────────────────────────────────────

    public function validateDiscount(string $code, ?int $productId = null): array
    {
        $discount = DiscountCode::where('code', $code)->first();

        if (! $discount) {
            return ['valid' => false, 'message' => 'Invalid discount code.'];
        }

        if (! $discount->isValid($productId)) {
            if ($discount->max_uses !== null && $discount->used_count >= $discount->max_uses) {
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

    public function availableGateways(): array
    {
        return array_values(array_filter([
            ['id' => 'paystack', 'name' => 'Paystack', 'configured' => filled(app(PaystackSettings::class)->secret())],
            ['id' => 'flutterwave', 'name' => 'Flutterwave', 'configured' => filled(config('services.flutterwave.secret_key'))],
            ['id' => 'stripe', 'name' => 'Stripe', 'configured' => filled(config('services.stripe.secret'))],
        ], fn ($gateway) => $gateway['configured']));
    }
}
