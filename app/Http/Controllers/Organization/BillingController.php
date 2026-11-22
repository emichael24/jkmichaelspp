<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrganizationCourseService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BillingController extends Controller
{
    public function index(Request $request, OrganizationCourseService $service)
    {
        $org = $service->organization($request->user());

        return Inertia::render('Organization/Billing/Index', ['organization' => $org->only('id', 'name'),
            'orders' => Order::where('organization_id', $org->id)->where('purchase_scope', 'organization')->with('items')->latest('id')->paginate(15)
                ->through(fn ($o) => [...$o->only('uuid', 'total', 'currency', 'status'), 'created_at' => $o->created_at->toDateTimeString(),
                    'places' => $o->items->sum('quantity'), 'test_mode' => data_get($o->billing_details, 'payment_mode') === 'test'])]);
    }

    public function show(Request $request, string $uuid, OrganizationCourseService $service)
    {
        $org = $service->organization($request->user());
        $order = Order::where('organization_id', $org->id)->where('purchase_scope', 'organization')->where('uuid', $uuid)->with(['items.product', 'payments'])->firstOrFail();

        return Inertia::render('Organization/Billing/Show', ['organization' => $org->only('id', 'name'), 'order' => [
            ...$order->only('uuid', 'subtotal', 'discount_amount', 'total', 'currency', 'status', 'billing_details'),
            'created_at' => $order->created_at->toDateTimeString(), 'paid_at' => $order->paid_at?->toDateTimeString(),
            'items' => $order->items->map(fn ($i) => [...$i->only('quantity', 'unit_price', 'total_price'), 'name' => $i->product_name ?? $i->product?->name]),
            'payments' => $order->payments->map(fn ($p) => $p->only('gateway', 'gateway_reference', 'status', 'amount', 'currency')),
        ]]);
    }
}
