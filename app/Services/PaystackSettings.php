<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\PaymentGatewaySetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PaystackSettings
{
    public function mode(): string
    {
        return $this->setting()?->active_mode ?: $this->environmentMode();
    }

    private function setting(): ?PaymentGatewaySetting
    {
        return Schema::hasTable('payment_gateway_settings') ? PaymentGatewaySetting::where('provider', 'paystack')->first() : null;
    }

    private function environmentMode(): string
    {
        return str_starts_with((string) config('services.paystack.secret_key'), 'sk_live_') ? 'live' : 'test';
    }

    private function credentials(string $mode, ?PaymentGatewaySetting $setting = null): array
    {
        $setting ??= $this->setting();
        return $setting?->credentials[$mode] ?? ($this->environmentMode() === $mode ? [
            'secret' => (string) config('services.paystack.secret_key'),
            'public' => (string) config('services.paystack.public_key'),
        ] : ['secret' => '', 'public' => '']);
    }

    public function secret(?string $mode = null): string
    {
        return $this->credentials($mode ?? $this->mode())['secret'] ?? '';
    }

    public function displayMode(): string
    {
        return $this->secret() !== '' ? $this->mode() : 'unconfigured';
    }

    public function summary(): array
    {
        $setting = PaymentGatewaySetting::where('provider', 'paystack')->firstOrFail();
        $profiles = [];
        foreach (['test', 'live'] as $mode) {
            $keys = $this->credentials($mode, $setting);
            $profiles[$mode] = [
                'configured' => filled($keys['secret']) && filled($keys['public']),
                'secret_hint' => filled($keys['secret']) ? '••••'.substr($keys['secret'], -4) : null,
                'public_hint' => filled($keys['public']) ? '••••'.substr($keys['public'], -4) : null,
                'source' => isset($setting->credentials[$mode]) ? 'admin' : 'server',
                'verified_at' => $keys['verified_at'] ?? null,
            ];
        }
        return ['mode' => $this->mode(), 'configured' => $this->secret() !== '', 'version' => $setting->version,
            'profiles' => $profiles, 'webhook_url' => url('/webhooks/paystack')];
    }

    public function signedMode(string $payload, ?string $signature): ?string
    {
        if (! is_string($signature) || ! preg_match('/^[a-f0-9]{128}$/i', $signature)) return null;
        foreach (['test', 'live'] as $mode) {
            $secret = $this->secret($mode);
            if ($secret !== '' && hash_equals(hash_hmac('sha512', $payload, $secret), $signature)) return $mode;
        }
        return null;
    }

    public function update(array $data): void
    {
        $mode = $data['mode'];
        $setting = PaymentGatewaySetting::where('provider', 'paystack')->firstOrFail();
        $keys = $this->credentials($mode, $setting);
        $replacing = filled($data['paystack_secret_key'] ?? null) || filled($data['paystack_public_key'] ?? null);
        if ($replacing) {
            foreach (['secret' => 'paystack_secret_key', 'public' => 'paystack_public_key'] as $type => $field) {
                $prefix = $type === 'secret' ? 'sk' : 'pk';
                if (! preg_match('/^'.$prefix.'_'.$mode.'_[a-zA-Z0-9]{20,128}$/', $data[$field] ?? '')) {
                    throw ValidationException::withMessages([$field => 'Enter both '.$mode.' keys from the same Paystack account.']);
                }
                $keys[$type] = $data[$field];
            }
        }
        if (empty($keys['secret']) || empty($keys['public'])) {
            throw ValidationException::withMessages(['paystack_secret_key' => 'Add both keys for the selected mode before saving.']);
        }
        if ($data['activate'] && $mode === 'live' && $this->mode() !== 'live' && ! ($data['confirm_live'] ?? false)) {
            throw ValidationException::withMessages(['confirm_live' => 'Confirm that new checkouts will charge real money.']);
        }
        // A read-only authenticated check: no payment, customer, or transfer is created.
        try {
            $response = Http::withToken($keys['secret'])->connectTimeout(5)->timeout(15)->get('https://api.paystack.co/balance');
        } catch (ConnectionException) {
            throw ValidationException::withMessages(['paystack_secret_key' => 'Paystack could not be reached. Nothing changed; try again shortly.']);
        }
        if (! $response->successful() || $response->json('status') !== true) {
            throw ValidationException::withMessages(['paystack_secret_key' => 'Paystack did not accept this secret key. Check the key and any Paystack IP restrictions. Nothing changed.']);
        }
        $keys['verified_at'] = now()->toIso8601String();
        DB::transaction(function () use ($data, $mode, $keys, $replacing) {
            $setting = PaymentGatewaySetting::where('provider', 'paystack')->lockForUpdate()->firstOrFail();
            if ($setting->version !== (int) $data['version']) {
                throw ValidationException::withMessages(['version' => 'Another administrator changed these settings. Reload this page before saving.']);
            }
            $previous = $setting->active_mode ?: $this->environmentMode();
            $profiles = $setting->credentials ?? [];
            // Retain the other environment for refunds and late payment notifications.
            foreach (['test', 'live'] as $candidate) {
                $profiles[$candidate] ??= $this->credentials($candidate, $setting);
            }
            $profiles[$mode] = $keys;
            $setting->update(['credentials' => $profiles, 'active_mode' => $data['activate'] ? $mode : $previous, 'version' => $setting->version + 1]);
            AuditLog::log('payment_settings.updated', PaymentGatewaySetting::class, $setting->id,
                ['mode' => $previous], ['mode' => $setting->active_mode, 'profile' => $mode, 'credentials_replaced' => $replacing, 'provider_check' => 'passed']);
        }, 3);
    }
}
