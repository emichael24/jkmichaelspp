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

        foreach (SecurityService::getSecurityHeaders() as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }
}
