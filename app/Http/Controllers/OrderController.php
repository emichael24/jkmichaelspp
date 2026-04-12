<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\PaymentService;
use App\Services\NotificationService;
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
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'sometimes|integer|min:1',
            'discount_code' => 'nullable|string',
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
        if ($checkoutUrl) {
            return Inertia::location($checkoutUrl);
        }

        // Fallback: render order page
        return redirect()->route('orders.show', $result['order']->uuid)
            ->with('warning', 'Payment gateway could not be initialized. Please try again.');
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

    /**
     * Order history.
     */
    public function index(Request $request)
    {
        $orders = Order::forUser(auth()->id())
            ->with(['items.product', 'latestPayment'])
            ->latest()
            ->paginate(10)
            ->through(fn($order) => [
                'id' => $order->id,
                'uuid' => $order->uuid,
                'total' => $order->total,
                'currency' => $order->currency,
                'status' => $order->status,
                'paid_at' => $order->paid_at?->format('M d, Y'),
                'created_at' => $order->created_at->format('M d, Y'),
                'items_count' => $order->items->count(),
                'gateway' => $order->latestPayment?->gateway,
                'items' => $order->items->map(fn($i) => [
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
            ->where('user_id', auth()->id())
            ->with(['items.product', 'payments', 'discountCode'])
            ->firstOrFail();

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
                'items' => $order->items->map(fn($i) => [
                    'product_name' => $i->product?->name,
                    'quantity' => $i->quantity,
                    'unit_price' => $i->unit_price,
                    'total_price' => $i->total_price,
                ]),
                'payments' => $order->payments->map(fn($p) => [
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
