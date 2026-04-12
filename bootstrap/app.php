<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'payments/webhook/*',
        ]);

        $middleware->alias([
            'role'       => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // When Spatie role middleware denies access, redirect user to their own dashboard
        $exceptions->renderable(function (\Spatie\Permission\Exceptions\UnauthorizedException $e, $request) {
            $user = $request->user();
            if ($user) {
                $role = $user->getRoleNames()->first() ?? 'learner';
                $destination = match ($role) {
                    'platform_admin' => '/admin',
                    'facilitator'    => '/facilitator',
                    'sme_reviewer'   => '/sme/dashboard',
                    'sponsor'        => '/sponsor',
                    default          => '/dashboard',
                };

                return redirect($destination)->with('error', 'You do not have permission to access that area.');
            }

            return redirect('/login')->with('error', 'Please sign in to continue.');
        });
    })->create();
