<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\NotificationService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private NotificationService $notificationService,
    ) {}

    /**
     * Create order + initiate payment.
     */
    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1|max:50',
            'items.*.product_id' => 'required|integer|distinct|exists:products,id',
            'items.*.quantity' => 'sometimes|integer|in:1',
            'discount_code' => 'nullable|string|max:100',
            'payment_gateway' => 'required|in:paystack,flutterwave,stripe',
        ]);

        $result = $this->paymentService->createOrder(
            auth()->user(),
            $request->items,
            $request->discount_code,
            $request->payment_gateway,
        );

        // If we have a checkout URL, redirect
        $checkoutUrl = $result['checkout']['authorization_url'] ?? null;
        if ($result['checkout']['completed'] ?? false) {
            return redirect()->route('orders.show', $result['order']->uuid)->with('success', 'Your order is complete. No payment was required.');
        }
        if ($checkoutUrl) {
            return Inertia::location($checkoutUrl);
        }

        // Fallback: render order page
        return redirect()->route('orders.show', $result['order']->uuid)
            ->with('warning', $result['checkout']['error'] ?? 'The provider could not prepare checkout. Your order and payment reference are retained.');
    }

    /**
     * Validate discount code (AJAX).
     */
    public function validateDiscount(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'product_id' => 'nullable|integer',
        ]);

        $result = $this->paymentService->validateDiscount($request->code, $request->product_id);

        return response()->json($result);
    }

    public function resume(Request $request, string $uuid)
    {
        $order = Order::where('uuid', $uuid)->firstOrFail();
        abort_unless($order->accessibleBy($request->user()), 404);
        $checkout = $this->paymentService->resumeCheckout($order, $request->user());
        if ($checkout['authorization_url'] ?? null) {
            return Inertia::location($checkout['authorization_url']);
        }

        return back()->with(($checkout['completed'] ?? false) ? 'success' : 'warning', ($checkout['completed'] ?? false) ? 'Order completed.' : ($checkout['error'] ?? 'The provider could not prepare checkout. Your order is retained.'));
    }

    public function refreshPayment(Request $request, string $uuid)
    {
        $order = Order::where('uuid', $uuid)->firstOrFail();
        abort_unless($order->accessibleBy($request->user()), 404);
        $paid = $this->paymentService->refreshOrder($order, $request->user());

        return back()->with($paid ? 'success' : 'info', $paid ? 'Payment verified. Your order is complete.' : 'The provider has not confirmed a successful payment yet.');
    }

    /**
     * Order history.
     */
    public function index(Request $request)
    {
        $orders = Order::forUser(auth()->id())
            ->where('purchase_scope', 'individual')
            ->with(['items.product', 'latestPayment'])
            ->latest()
            ->paginate(10)
            ->through(fn ($order) => [
                'id' => $order->id,
                'uuid' => $order->uuid,
                'total' => $order->total,
                'currency' => $order->currency,
                'status' => $order->status,
                'paid_at' => $order->paid_at?->format('M d, Y'),
                'created_at' => $order->created_at->format('M d, Y'),
                'items_count' => $order->items->count(),
                'gateway' => $order->latestPayment?->gateway,
                'items' => $order->items->map(fn ($i) => [
                    'product_name' => $i->product?->name,
                    'quantity' => $i->quantity,
                    'total_price' => $i->total_price,
                ]),
            ]);

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
        ]);
    }

    /**
     * Order detail / confirmation page.
     */
    public function show(string $uuid, Request $request)
    {
        $order = Order::where('uuid', $uuid)
            ->with(['items.product', 'payments', 'discountCode'])
            ->firstOrFail();
        abort_unless($order->accessibleBy($request->user()), 404);
        if ($order->purchase_scope === 'organization') return redirect('/organization/billing/'.$order->uuid);

        // Check if returning from gateway
        $gatewayReturn = $request->has('gateway');

        return Inertia::render('Orders/Show', [
            'order' => [
                'id' => $order->id,
                'uuid' => $order->uuid,
                'subtotal' => $order->subtotal,
                'discount_amount' => $order->discount_amount,
                'total' => $order->total,
                'currency' => $order->currency,
                'status' => $order->status,
                'paid_at' => $order->paid_at?->format('M d, Y H:i'),
                'created_at' => $order->created_at->format('M d, Y H:i'),
                'discount_code' => $order->discountCode?->code,
                'items' => $order->items->map(fn ($i) => [
                    'product_name' => $i->product?->name,
                    'quantity' => $i->quantity,
                    'unit_price' => $i->unit_price,
                    'total_price' => $i->total_price,
                ]),
                'payments' => $order->payments->map(fn ($p) => [
                    'gateway' => $p->gateway,
                    'amount' => $p->amount,
                    'status' => $p->status,
                    'paid_at' => $p->paid_at?->format('M d, Y H:i'),
                ]),
            ],
            'gatewayReturn' => $gatewayReturn,
        ]);
    }
}
