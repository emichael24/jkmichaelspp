<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OrganizationInvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class InvitationController extends Controller
{
    public function show(Request $request, string $token, OrganizationInvitationService $service)
    {
        $invite = $service->resolve($token);
        $request->session()->put('url.intended', route('organization.invitation.show', $token));

        return Inertia::render('Organization/AcceptInvitation', [
            'invitation' => ['organization' => $invite->organization->name, 'email' => $invite->email, 'first_name' => $invite->first_name,
                'last_name' => $invite->last_name, 'expires_at' => $invite->expires_at->toIso8601String()],
            'existingAccount' => User::withTrashed()->whereRaw('LOWER(email) = ?', [$invite->email])->exists(),
            'signedInEmail' => $request->user()?->email, 'acceptUrl' => route('organization.invitation.accept', $token),
        ])->toResponse($request)->withHeaders(['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']);
    }

    public function accept(Request $request, string $token, OrganizationInvitationService $service)
    {
        $user = $service->accept($token, $request->user(), $request->only(['first_name', 'last_name', 'password', 'password_confirmation']));
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('url.intended');

        return redirect('/dashboard')->with('success', 'You have joined your organization. Welcome to JK Michaels.');
    }
}
