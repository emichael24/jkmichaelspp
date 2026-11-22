<?php

namespace App\Http\Middleware;

use App\Services\PlatformSettings;
use Closure;
use Illuminate\Http\Request;

class EnforcePlatformSettings
{
    public function handle(Request $request, Closure $next)
    {
        $settings = app(PlatformSettings::class)->all();
        config(['app.name' => $settings['platform_name']]);
        if ($request->user() && $settings['require_email_verification'] && ! $request->user()->email_verified_at
            && ! $request->routeIs('verification.*', 'logout', 'login') && ! $request->is('scorm-assets/*')) {
            if ($request->expectsJson()) {
                abort(403, 'Verify your email before continuing.');
            }

            return redirect()->route('verification.notice');
        }

        return $next($request);
    }
}
