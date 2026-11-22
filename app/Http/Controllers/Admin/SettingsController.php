<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WhatsappSession;
use App\Services\PlatformSettings;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(protected WhatsAppService $whatsapp) {}

    /**
     * Show platform settings.
     */
    public function index(): Response
    {
        $waSessions = WhatsappSession::select('session_id', 'name', 'status')->get();
        $pairedCount = User::whereNotNull('whatsapp_number')->count();

        $settings = [
            'platform_name' => config('app.name', 'JK Michaels'),
            'platform_url' => config('app.url'),
            'timezone' => config('app.timezone', 'Africa/Lagos'),
            'locale' => config('app.locale', 'en'),
            'mail_from_name' => config('mail.from.name'),
            'mail_from_address' => config('mail.from.address'),
            'storage_disk' => config('filesystems.default'),
            'max_upload_size' => ini_get('upload_max_filesize'),
            'payment_gateways' => [
                'paystack' => filled(app(\App\Services\PaystackSettings::class)->secret()),
                'flutterwave' => ! empty(config('services.flutterwave.secret_key')),
                'stripe' => ! empty(config('services.stripe.secret')),
            ],
            'notifications' => [
                'push_enabled' => true,
                'email_enabled' => true,
                'max_push_per_day' => 3,
            ],
            'data_retention_days' => 365,
            'allow_registration' => true,
            'require_email_verification' => true,
            'default_wa_session' => config('services.flexichurch.whatsapp_session'),
        ];

        $settings = array_replace($settings, app(PlatformSettings::class)->all());

        return Inertia::render('Admin/Settings/Index', [
            'settings' => $settings,
            'waSessions' => $waSessions,
            'waPairedCount' => $pairedCount,
        ]);
    }

    /**
     * Update platform settings.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'platform_name' => 'string|max:255',
            'timezone' => 'timezone',
            'locale' => 'in:en',
            'notifications.push_enabled' => 'boolean',
            'notifications.email_enabled' => 'boolean',
            'notifications.max_push_per_day' => 'integer|min:1|max:20',
            'data_retention_days' => 'integer|min:30|max:3650',
            'allow_registration' => 'boolean',
            'require_email_verification' => 'boolean',
        ]);

        app(PlatformSettings::class)->put($validated);

        return back()->with('success', 'Settings updated successfully.');
    }

    // ─── WhatsApp Number Pairing ──────────────────────────────────────────────────

    /**
     * List all users with their WhatsApp pairing info.
     */
    public function waPairings(Request $request): Response
    {
        $query = User::select('id', 'first_name', 'last_name', 'email', 'phone', 'whatsapp_number', 'whatsapp_session', 'whatsapp_paired_at', 'whatsapp_paired_by', 'avatar', 'is_active')
            ->with('pairedBy:id,first_name,last_name')
            ->withOnly([]);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('whatsapp_number', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('paired_only')) {
            $query->whereNotNull('whatsapp_number');
        }

        if ($request->boolean('unpaired_only')) {
            $query->whereNull('whatsapp_number');
        }

        if ($role = $request->input('role')) {
            $query->role($role);
        }

        $users = $query->orderBy('first_name')->paginate(30)->withQueryString();
        $sessions = WhatsappSession::select('session_id', 'name', 'status')->get();

        return Inertia::render('Admin/Settings/WhatsAppPairings', [
            'users' => $users,
            'sessions' => $sessions,
            'filters' => $request->only(['search', 'paired_only', 'unpaired_only', 'role']),
            'stats' => [
                'total' => User::count(),
                'paired' => User::whereNotNull('whatsapp_number')->count(),
                'unpaired' => User::whereNull('whatsapp_number')->count(),
            ],
        ]);
    }

    /**
     * Pair a WhatsApp number to a single user.
     */
    public function pairUser(Request $request, User $user)
    {
        $validated = $request->validate([
            'whatsapp_number' => ['required', 'string', 'max:30', 'regex:/^\+?[1-9]\d{6,19}$/'],
            'whatsapp_session' => ['nullable', 'string', 'max:100', 'exists:whatsapp_sessions,session_id'],
        ]);

        $user->update([
            'whatsapp_number' => $validated['whatsapp_number'],
            'whatsapp_session' => $validated['whatsapp_session'] ?? null,
            'whatsapp_paired_at' => now(),
            'whatsapp_paired_by' => auth()->id(),
        ]);

        return back()->with('success', "WhatsApp number paired to {$user->full_name}.");
    }

    /**
     * Remove the WhatsApp pairing from a user.
     */
    public function unpairUser(User $user)
    {
        $user->update([
            'whatsapp_number' => null,
            'whatsapp_session' => null,
            'whatsapp_paired_at' => null,
            'whatsapp_paired_by' => null,
        ]);

        return back()->with('success', "WhatsApp number removed from {$user->full_name}.");
    }

    /**
     * Bulk-pair users from a CSV-style import.
     * Accepts either user_id OR email per entry.
     */
    public function bulkPairUsers(Request $request)
    {
        $request->validate([
            'pairs' => ['required', 'array', 'min:1', 'max:500'],
            'pairs.*.whatsapp_number' => ['required', 'string', 'max:30', 'regex:/^\+?[1-9]\d{6,19}$/'],
            'pairs.*.whatsapp_session' => ['nullable', 'string', 'max:100', 'exists:whatsapp_sessions,session_id'],
            // Either email or user_id must be provided
            'pairs.*.email' => ['nullable', 'email', 'max:255'],
            'pairs.*.user_id' => ['nullable', 'integer'],
        ]);

        $adminId = auth()->id();
        $now = now();
        $count = 0;
        $skipped = 0;

        foreach ($request->input('pairs') as $pair) {
            if (! empty($pair['user_id'])) {
                $user = User::find($pair['user_id']);
            } elseif (! empty($pair['email'])) {
                $user = User::where('email', $pair['email'])->first();
            } else {
                $skipped++;

                continue;
            }

            if (! $user) {
                $skipped++;

                continue;
            }

            $user->update([
                'whatsapp_number' => $pair['whatsapp_number'],
                'whatsapp_session' => $pair['whatsapp_session'] ?? null,
                'whatsapp_paired_at' => $now,
                'whatsapp_paired_by' => $adminId,
            ]);
            $count++;
        }

        return back()->with('success', "{$count} user(s) updated".($skipped ? ", {$skipped} skipped (not found)." : '.'));
    }

    /**
     * Auto-pair: copy each user's existing phone number as their WA number
     * (for bulk bootstrapping — only affects users without a WA number already).
     */
    public function autoPairFromPhone(Request $request)
    {
        $request->validate(['default_session' => 'nullable|string|max:100|exists:whatsapp_sessions,session_id']);
        $updated = User::whereNull('whatsapp_number')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->update([
                'whatsapp_number' => DB::raw('phone'),
                'whatsapp_session' => $request->input('default_session'),
                'whatsapp_paired_at' => now(),
                'whatsapp_paired_by' => auth()->id(),
            ]);

        return back()->with('success', "{$updated} user(s) auto-paired from their phone number.");
    }
}
