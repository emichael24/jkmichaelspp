<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PaystackService
{
    protected string $secretKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->secretKey = config('services.paystack.secret_key');
        $this->baseUrl = config('services.paystack.payment_url', 'https://api.paystack.co');
    }

    /**
     * Initialize a transaction — returns authorization_url and reference.
     */
    public function initializeTransaction(array $params): array
    {
        $reference = $params['reference'] ?? 'JKM-' . strtoupper(Str::random(12));

        $payload = [
            'email'     => $params['email'],
            'amount'    => (int) round($params['amount'] * 100), // kobo
            'reference' => $reference,
            'callback_url' => $params['callback_url'] ?? config('services.paystack.callback_url'),
            'metadata'  => $params['metadata'] ?? [],
            'currency'  => $params['currency'] ?? 'NGN',
            'channels'  => $params['channels'] ?? ['card', 'bank', 'ussd', 'qr', 'bank_transfer'],
        ];

        if (! empty($params['plan'])) {
            $payload['plan'] = $params['plan'];
        }

        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/transaction/initialize", $payload);

        $data = $response->json();

        if (! $response->successful() || ! ($data['status'] ?? false)) {
            throw new \RuntimeException('Paystack initialization failed: ' . ($data['message'] ?? 'Unknown error'));
        }

        return [
            'reference'       => $reference,
            'access_code'     => $data['data']['access_code'],
            'authorization_url' => $data['data']['authorization_url'],
        ];
    }

    /**
     * Verify a transaction by reference.
     */
    public function verifyTransaction(string $reference): array
    {
        $response = Http::withToken($this->secretKey)
            ->get("{$this->baseUrl}/transaction/verify/{$reference}");

        $data = $response->json();

        if (! $response->successful() || ! ($data['status'] ?? false)) {
            throw new \RuntimeException('Paystack verification failed: ' . ($data['message'] ?? 'Unknown error'));
        }

        return $data['data'];
    }

    /**
     * Create a Paystack customer.
     */
    public function createCustomer(array $params): array
    {
        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/customer", $params);

        $data = $response->json();

        if (! $response->successful() || ! ($data['status'] ?? false)) {
            throw new \RuntimeException('Paystack customer creation failed: ' . ($data['message'] ?? 'Unknown error'));
        }

        return $data['data'];
    }

    /**
     * Create a subscription for a customer and plan.
     */
    public function createSubscription(string $customerCode, string $planCode): array
    {
        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/subscription", [
                'customer' => $customerCode,
                'plan' => $planCode,
            ]);

        $data = $response->json();

        if (! $response->successful() || ! ($data['status'] ?? false)) {
            throw new \RuntimeException('Paystack subscription creation failed: ' . ($data['message'] ?? 'Unknown error'));
        }

        return $data['data'];
    }

    /**
     * Validate Paystack webhook signature.
     */
    public function validateWebhook(string $payload, string $signature): bool
    {
        $secret = config('services.paystack.webhook_secret') ?: $this->secretKey;
        $expected = hash_hmac('sha512', $payload, $secret);
        return hash_equals($expected, $signature);
    }

    /**
     * Fetch list of banks (for bank transfers).
     */
    public function listBanks(string $country = 'nigeria'): array
    {
        $response = Http::withToken($this->secretKey)
            ->get("{$this->baseUrl}/bank", ['country' => $country, 'perPage' => 100]);

        return $response->json('data', []);
    }

    /**
     * Create a transfer recipient (for payouts).
     */
    public function createTransferRecipient(array $params): array
    {
        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/transferrecipient", $params);

        $data = $response->json();
        if (! $response->successful() || ! ($data['status'] ?? false)) {
            throw new \RuntimeException('Failed to create transfer recipient: ' . ($data['message'] ?? ''));
        }

        return $data['data'];
    }

    /**
     * Initiate a transfer (payout).
     */
    public function initiateTransfer(array $params): array
    {
        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/transfer", array_merge([
                'source'   => 'balance',
                'currency' => 'NGN',
            ], $params));

        $data = $response->json();
        if (! $response->successful() || ! ($data['status'] ?? false)) {
            throw new \RuntimeException('Transfer failed: ' . ($data['message'] ?? ''));
        }

        return $data['data'];
    }
}
