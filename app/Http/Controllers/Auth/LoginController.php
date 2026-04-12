<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class LoginController extends Controller
{
    public function create()
    {
        return Inertia::render('Auth/Login');
    }

    public function store(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::user();
        $user->update(['last_login_at' => now()]);

        // Role-based redirect
        $role = $user->getRoleNames()->first() ?? 'learner';
        $destination = match ($role) {
            'platform_admin' => route('admin.dashboard'),
            'facilitator'    => route('facilitator.dashboard'),
            'sme_reviewer'   => route('sme.dashboard'),
            'sponsor'        => route('sponsor.dashboard'),
            default          => route('dashboard'),
        };

        return redirect()->intended($destination);
    }

    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
