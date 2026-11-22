<?php

namespace App\Http\Middleware;

use App\Models\UserGamification;
use App\Services\SpacedRepetitionService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'paymentMode' => app(\App\Services\PaystackSettings::class)->displayMode(),
            'auth' => [
                'offline_context' => $user ? hash_hmac('sha256', $request->session()->getId(), config('app.key')) : null,
                'user' => $user ? [
                    'id' => $user->id,
                    'uuid' => $user->uuid,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'name' => $user->full_name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'initials' => $user->initials,
                    'roles' => $user->getRoleNames(),
                    'role' => $user->getRoleNames()->first() ?? 'learner',
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                    'level' => fn () => UserGamification::forUser($user->id)->level ?? 1,
                    'xp' => fn () => UserGamification::forUser($user->id)->total_xp ?? 0,
                    'next_level_xp' => fn () => UserGamification::forUser($user->id)->xpForNextLevel() ?? 1000,
                    'xp_progress' => fn () => UserGamification::forUser($user->id)->xpProgressPct() ?? 0,
                    'streak' => fn () => UserGamification::forUser($user->id)->current_streak_days ?? 0,
                    'onboarding_completed' => $user->onboarding_completed,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],
            'notificationCount' => fn () => $user ? ($user->unreadNotifications()->count() ?? 0) : 0,
            'dueCards' => fn () => $user ? (app(SpacedRepetitionService::class)->getSessionStats($user->id)['due_today'] ?? 0) : 0,
        ];
    }
}
