<?php

use App\Http\Middleware\EnforcePlatformSettings;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SerializeCurriculumWrites;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trimStrings(except: ['admin_password', 'admin_password_confirmation']);
        $middleware->web(append: [
            EnsureAccountIsActive::class,
            EnforcePlatformSettings::class,
            SerializeCurriculumWrites::class,
            HandleInertiaRequests::class,
            SecurityHeaders::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'payments/webhook/*',
            'webhooks/paystack',
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['admin_password', 'admin_password_confirmation', 'paystack_secret_key', 'paystack_public_key', 'current_password']);
        // When Spatie role middleware denies access, redirect user to their own dashboard
        $exceptions->renderable(function (UnauthorizedException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'You do not have permission to access that area.'], 403);
            }
            $user = $request->user();
            if ($user) {
                $role = $user->getRoleNames()->first() ?? 'learner';
                $destination = match ($role) {
                    'platform_admin' => '/admin',
                    'facilitator' => '/facilitator',
                    'sme_reviewer' => '/sme/dashboard',
                    'sponsor' => '/sponsor',
                    'org_admin' => '/organization',
                    default => '/dashboard',
                };

                return redirect($destination)->with('error', 'You do not have permission to access that area.');
            }

            return redirect('/login')->with('error', 'Please sign in to continue.');
        });
    })->create();
