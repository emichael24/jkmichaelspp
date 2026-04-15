<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Order;
use App\Models\Payment;
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

        // Revenue summary
        $totalRevenue = Payment::where('status', 'success')->sum('amount');
        $monthlyRevenue = Payment::where('status', 'success')
            ->where('created_at', '>=', $thirtyDaysAgo)->sum('amount');
        $totalOrders = Order::where('status', 'paid')->count();

        // Revenue by gateway
        $revenueByGateway = Payment::where('status', 'success')
            ->selectRaw('gateway, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('gateway')
            ->get();

        // Recent transactions
        $recentPayments = Payment::with(['order.user:id,first_name,last_name,email'])
            ->orderByDesc('created_at')
            ->paginate(20);

        // Monthly revenue trend
        $monthlyTrend = Payment::where('status', 'success')
            ->where('created_at', '>=', now()->subMonths(12))
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, SUM(amount) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month');

        // Active seats (users with active enrollments)
        $activeSeats = User::whereHas('enrollments', fn($q) => $q->where('status', 'active'))
            ->where('is_active', true)
            ->count();

        return Inertia::render('Admin/Billing/Index', [
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
        $payment = Payment::findOrFail($paymentId);

        if ($payment->status !== 'successful') {
            return back()->with('error', 'Only successful payments can be refunded.');
        }

        $payment->markRefunded();

        // Update order status
        if ($payment->order) {
            $payment->order->update(['status' => 'refunded']);
        }

        return back()->with('success', 'Payment has been marked as refunded.');
    }
}
