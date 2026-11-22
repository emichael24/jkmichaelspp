<?php

namespace App\Http\Middleware;

use App\Services\SecurityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Add security headers to all responses.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user() && ! $request->is('offline')) {
            $response->headers->removeCacheControlDirective('public');
            $response->headers->addCacheControlDirective('private');
            $response->headers->addCacheControlDirective('no-store');
            $response->headers->addCacheControlDirective('max-age', 0);
        }

        foreach (SecurityService::getSecurityHeaders() as $key => $value) {
            if (! $response->headers->has($key)) {
                $response->headers->set($key, $value);
            }
        }

        return $response;
    }
}
