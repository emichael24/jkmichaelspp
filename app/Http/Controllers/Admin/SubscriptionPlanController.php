<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Plan;

class SubscriptionPlanController extends Controller
{
    public function index()
    {
        $plans = Plan::with('features')->orderBy('sort_order')->get();

        return Inertia::render('Admin/Subscriptions/PlansIndex', [
            'plans' => $plans
        ]);
    }

    public function generateInvoiceLink(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
            'email' => 'required|email',
            'organization_name' => 'required|string',
        ]);

        $plan = Plan::findOrFail($request->plan_id);

        // Typical flow for B2B Paystack invoice link:
        // We'd hit Paystack's API to create a Payment Request / Invoice.

        $mockLink = "https://paystack.com/pay/b2b-invoice-" . uniqid();

        return redirect()->back()->with('success', "Invoice link generated: $mockLink");
    }

    public function update(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'paystack_plan_code' => 'nullable|string|max:255',
        ]);

        $plan->update($validated);

        return back()->with('success', 'Plan updated.');
    }
}
