<?php

namespace App\Http\Middleware;

use App\Services\DeviceSessionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $inactiveOrganization = $user?->organization_id && (! $user->organization || ! $user->organization->is_active);
        if ($user && (! $user->is_active || ($inactiveOrganization && ! $user->hasRole('platform_admin')))) {
            app(DeviceSessionService::class)->disconnect($request);
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            abort(403, 'Your account or organization is inactive. Contact your administrator.', ['Clear-Site-Data' => '"cache", "storage"']);
        }

        return $next($request);
    }
}
