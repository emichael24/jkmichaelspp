<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PaystackSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PaymentSettingsController extends Controller
{
    public function index(PaystackSettings $settings)
    {
        return Inertia::render('Admin/Settings/Payments', ['paystack' => $settings->summary()]);
    }

    public function update(Request $request, PaystackSettings $settings)
    {
        $data = $request->validate([
            'mode' => 'required|in:test,live', 'version' => 'required|integer|min:0',
            'paystack_secret_key' => 'nullable|string|max:150', 'paystack_public_key' => 'nullable|string|max:150',
            'activate' => 'required|boolean', 'confirm_live' => 'sometimes|boolean',
            'current_password' => 'required|string|current_password',
        ]);
        $settings->update($data);
        return to_route('admin.settings.payments')->with('success', $data['activate']
            ? 'Paystack settings verified. New checkouts now use '.$data['mode'].' mode.'
            : 'Paystack keys verified and saved. The active payment mode is unchanged.');
    }
}
