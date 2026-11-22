<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PaystackService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.paystack.payment_url', 'https://api.paystack.co');
    }

    /**
     * Initialize a transaction — returns authorization_url and reference.
     */
    public function initializeTransaction(array $params): array
    {
        $reference = $params['reference'] ?? 'JKM-'.strtoupper(Str::random(12));

        $payload = [
            'email' => $params['email'],
            'amount' => (int) round($params['amount'] * 100), // kobo
            'reference' => $reference,
            'callback_url' => $params['callback_url'] ?? config('services.paystack.callback_url'),
            'metadata' => $params['metadata'] ?? [],
            'currency' => $params['currency'] ?? 'NGN',
            'channels' => $params['channels'] ?? ['card', 'bank', 'ussd', 'qr', 'bank_transfer'],
        ];

        if (! empty($params['plan'])) {
            $payload['plan'] = $params['plan'];
        }

        $response = Http::withToken(app(PaystackSettings::class)->secret($params['payment_mode'] ?? null))->timeout(20)
            ->post("{$this->baseUrl}/transaction/initialize", $payload);

        $data = $response->json();

        if (! $response->successful() || ! ($data['status'] ?? false)) {
            throw new \RuntimeException('Paystack initialization failed: '.($data['message'] ?? 'Unknown error'));
        }

        return [
            'reference' => $reference,
            'access_code' => $data['data']['access_code'],
            'authorization_url' => $data['data']['authorization_url'],
        ];
    }

    /**
     * Verify a transaction by reference.
     */
    public function verifyTransaction(string $reference, ?string $mode = null): array
    {
        $response = Http::withToken(app(PaystackSettings::class)->secret($mode))->timeout(20)
            ->get("{$this->baseUrl}/transaction/verify/{$reference}");

        $data = $response->json();

        if (! $response->successful() || ! ($data['status'] ?? false)) {
            throw new \RuntimeException('Paystack verification failed: '.($data['message'] ?? 'Unknown error'));
        }

        if (data_get($data, 'data.domain') && data_get($data, 'data.domain') !== ($mode ?: app(PaystackSettings::class)->mode())) {
            throw new \RuntimeException('Paystack returned a transaction from a different payment environment.');
        }

        return $data['data'];
    }

    /**
     * Create a Paystack customer.
     */
    public function createCustomer(array $params): array
    {
        $response = Http::withToken(app(PaystackSettings::class)->secret())->timeout(20)
            ->post("{$this->baseUrl}/customer", $params);

        $data = $response->json();

        if (! $response->successful() || ! ($data['status'] ?? false)) {
            throw new \RuntimeException('Paystack customer creation failed: '.($data['message'] ?? 'Unknown error'));
        }

        return $data['data'];
    }

    public function fetchSubscription(string $code, ?string $mode = null): array
    {
        $response = Http::withToken(app(PaystackSettings::class)->secret($mode))->timeout(20)->get($this->baseUrl.'/subscription/'.rawurlencode($code));
        if (! $response->successful() || ! $response->json('status')) {
            throw new \RuntimeException('Unable to reconcile subscription with Paystack.');
        }

        return $response->json('data');
    }

    public function disableSubscription(string $code, string $token, ?string $mode = null): void
    {
        $response = Http::withToken(app(PaystackSettings::class)->secret($mode))->timeout(20)->post($this->baseUrl.'/subscription/disable', ['code' => $code, 'token' => $token]);
        if (! $response->successful() || ! $response->json('status')) {
            throw new \RuntimeException('Paystack could not confirm cancellation.');
        }
    }

    /**
     * Create a subscription for a customer and plan.
     */
    public function createSubscription(string $customerCode, string $planCode): array
    {
        $response = Http::withToken(app(PaystackSettings::class)->secret())->timeout(20)
            ->post("{$this->baseUrl}/subscription", [
                'customer' => $customerCode,
                'plan' => $planCode,
            ]);

        $data = $response->json();

        if (! $response->successful() || ! ($data['status'] ?? false)) {
            throw new \RuntimeException('Paystack subscription creation failed: '.($data['message'] ?? 'Unknown error'));
        }

        return $data['data'];
    }

    /**
     * Validate Paystack webhook signature.
     */
    public function validateWebhook(string $payload, string $signature): bool
    {
        return app(PaystackSettings::class)->signedMode($payload, $signature) !== null;
    }

    /**
     * Fetch list of banks (for bank transfers).
     */
    public function listBanks(string $country = 'nigeria'): array
    {
        $response = Http::withToken(app(PaystackSettings::class)->secret())->timeout(20)
            ->get("{$this->baseUrl}/bank", ['country' => $country, 'perPage' => 100]);

        return $response->json('data', []);
    }

    /**
     * Create a transfer recipient (for payouts).
     */
    public function createTransferRecipient(array $params): array
    {
        $response = Http::withToken(app(PaystackSettings::class)->secret())->timeout(20)
            ->post("{$this->baseUrl}/transferrecipient", $params);

        $data = $response->json();
        if (! $response->successful() || ! ($data['status'] ?? false)) {
            throw new \RuntimeException('Failed to create transfer recipient: '.($data['message'] ?? ''));
        }

        return $data['data'];
    }

    /**
     * Initiate a transfer (payout).
     */
    public function initiateTransfer(array $params): array
    {
        $response = Http::withToken(app(PaystackSettings::class)->secret())->timeout(20)
            ->post("{$this->baseUrl}/transfer", array_merge([
                'source' => 'balance',
                'currency' => 'NGN',
            ], $params));

        $data = $response->json();
        if (! $response->successful() || ! ($data['status'] ?? false)) {
            throw new \RuntimeException('Transfer failed: '.($data['message'] ?? ''));
        }

        return $data['data'];
    }
}
