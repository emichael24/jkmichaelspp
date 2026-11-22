<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\PlatformSettings;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class RegisterController extends Controller
{
    public function create()
    {
        abort_unless(app(PlatformSettings::class)->get('allow_registration'), 403, 'Public registration is closed. Contact your organization administrator.');

        return Inertia::render('Auth/Register');
    }

    public function store(RegisterRequest $request)
    {
        abort_unless(app(PlatformSettings::class)->get('allow_registration'), 403, 'Public registration is closed.');
        $user = User::create($request->validated());

        $user->assignRole('learner');

        event(new Registered($user));

        Auth::login($user);

        return redirect()->intended(route('dashboard'));
    }
}
