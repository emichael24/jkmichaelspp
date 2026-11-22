<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentRefundService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    /**
     * Show billing overview.
     */
    public function index(Request $request): Response
    {
        $thirtyDaysAgo = now()->subDays(30);
        $request->validate(['currency' => ['nullable', 'regex:/^[A-Z]{3}$/']]);
        $currency = $request->input('currency', 'NGN');
        $payments = Payment::where('currency', $currency);

        // Revenue summary
        $totalRevenue = (clone $payments)->where('status', 'success')->sum('amount');
        $monthlyRevenue = (clone $payments)->where('status', 'success')
            ->where('created_at', '>=', $thirtyDaysAgo)->sum('amount');
        $totalOrders = Order::where('status', 'paid')->count();

        // Revenue by gateway
        $revenueByGateway = (clone $payments)->where('status', 'success')
            ->selectRaw('gateway, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('gateway')
            ->pluck('total', 'gateway');

        // Recent transactions
        $recentPayments = (clone $payments)->with(['user:id,first_name,last_name,email', 'refund'])
            ->orderByDesc('created_at')
            ->paginate(20)->withQueryString();

        // Monthly revenue trend
        $monthlyTrend = (clone $payments)->where('status', 'success')
            ->where('created_at', '>=', now()->subMonths(12))
            ->selectRaw('SUBSTR(created_at, 1, 7) as month, SUM(amount) as revenue')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Active seats (users with active enrollments)
        $activeSeats = User::whereHas('enrollments', fn ($q) => $q->where('status', 'active'))
            ->where('is_active', true)
            ->count();

        return Inertia::render('Admin/Billing/Index', [
            'currency' => $currency,
            'currencies' => Payment::distinct()->pluck('currency')->push('NGN')->unique()->values(),
            'totalRevenue' => (float) $totalRevenue,
            'monthlyRevenue' => (float) $monthlyRevenue,
            'activeSeats' => $activeSeats,
            'metrics' => [
                'totalRevenue' => $totalRevenue,
                'monthlyRevenue' => $monthlyRevenue,
                'totalOrders' => $totalOrders,
                'activeSeats' => $activeSeats,
            ],
            'revenueByGateway' => $revenueByGateway,
            'recentPayments' => $recentPayments,
            'monthlyTrend' => $monthlyTrend,
        ]);
    }

    /**
     * Handle refund.
     */
    public function refund(Request $request, int $paymentId)
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:1000', 'confirmed' => 'accepted']);
        $refund = app(PaymentRefundService::class)->request(Payment::findOrFail($paymentId), $request->user(), $data['reason']);

        return back()->with($refund->status === 'processed' ? 'success' : 'info', 'Refund status: '.$refund->status.'. The request is recorded; do not submit a separate provider refund.');
    }

    public function reconcileRefund(Request $request, int $paymentId)
    {
        $refund = Payment::findOrFail($paymentId)->refund()->firstOrFail();
        $refund = app(PaymentRefundService::class)->reconcile($refund);

        return back()->with('info', 'Verified refund status: '.$refund->status.'.');
    }
}
