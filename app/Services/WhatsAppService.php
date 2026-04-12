<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    protected string $apiKey;
    protected string $baseUrl;
    protected string $defaultSession;

    public function __construct()
    {
        $this->apiKey         = config('services.flexichurch.api_key');
        $this->baseUrl        = rtrim(config('services.flexichurch.base_url', 'https://flexichurch.com/api/v1/external'), '/');
        $this->defaultSession = config('services.flexichurch.whatsapp_session', 'jkmichaels-main');
    }

    protected function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken($this->apiKey)
            ->baseUrl($this->baseUrl)
            ->timeout(30);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Messaging
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Send a single text message.
     */
    public function sendText(string $number, string $message): array
    {
        $response = $this->client()->post('/whatsapp/send', [
            'number'  => $this->normalizeNumber($number),
            'message' => $message,
        ]);

        $data = $response->json();

        if (! $response->successful() || ($data['status'] ?? '') !== 'success') {
            throw new \RuntimeException('WhatsApp send failed: ' . ($data['message'] ?? 'Unknown error'));
        }

        return $data['data'];
    }

    /**
     * Send a media message (image, video, audio, document).
     */
    public function sendMedia(string $number, string $mediaUrl, string $caption = ''): array
    {
        $payload = [
            'number'    => $this->normalizeNumber($number),
            'media_url' => $mediaUrl,
        ];

        if ($caption) {
            $payload['caption'] = $caption;
        }

        $response = $this->client()->post('/whatsapp/send-media', $payload);
        $data = $response->json();

        if (! $response->successful() || ($data['status'] ?? '') !== 'success') {
            throw new \RuntimeException('WhatsApp media send failed: ' . ($data['message'] ?? ''));
        }

        return $data['data'];
    }

    /**
     * Send bulk messages to multiple recipients (max 500 per call).
     * $recipients = [['number' => '234...', 'message' => '...'], ...]
     */
    public function sendBulk(array $recipients, string $defaultMessage = ''): array
    {
        // Normalize numbers
        $normalized = array_map(function ($r) {
            $r['number'] = $this->normalizeNumber($r['number']);
            return $r;
        }, $recipients);

        $payload = ['recipients' => $normalized];
        if ($defaultMessage) {
            $payload['message'] = $defaultMessage;
        }

        $response = $this->client()->post('/whatsapp/send-bulk', $payload);
        $data = $response->json();

        if (! $response->successful() || ($data['status'] ?? '') !== 'success') {
            throw new \RuntimeException('WhatsApp bulk send failed: ' . ($data['message'] ?? ''));
        }

        return $data['data'];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Sessions
    // ─────────────────────────────────────────────────────────────────────────

    public function listSessions(): array
    {
        $response = $this->client()->get('/whatsapp/sessions');
        return $response->json('data', []);
    }

    public function createSession(string $sessionName, string $webhookUrl = ''): array
    {
        $payload = ['session_name' => $sessionName];
        if ($webhookUrl) {
            $payload['webhook_url'] = $webhookUrl;
        }

        $response = $this->client()->post('/whatsapp/sessions', $payload);
        return $response->json('data', []);
    }

    public function getSession(string $sessionId): array
    {
        $response = $this->client()->get("/whatsapp/sessions/{$sessionId}");
        return $response->json('data', []);
    }

    public function getQrCode(string $sessionId): array
    {
        $response = $this->client()->get("/whatsapp/sessions/{$sessionId}/qr");
        return $response->json('data', []);
    }

    public function logoutSession(string $sessionId): array
    {
        $response = $this->client()->post("/whatsapp/sessions/{$sessionId}/logout");
        return $response->json('data', []);
    }

    public function gatewayStatus(): array
    {
        $response = $this->client()->timeout(10)->get('/whatsapp/status');
        return $response->json('data', []);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Normalize a phone number to international format (no + or spaces).
     * Nigerian numbers: 0801... → 234801...
     */
    public function normalizeNumber(string $number): string
    {
        $number = preg_replace('/\D/', '', $number);

        // Handle Nigerian local format (08..., 07..., 09...)
        if (strlen($number) === 11 && str_starts_with($number, '0')) {
            $number = '234' . substr($number, 1);
        }

        return $number;
    }
}
