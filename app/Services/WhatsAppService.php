<?php

namespace App\Services;

use App\Exceptions\ExternalIntegrationException;

class WhatsAppService
{
    public function __construct(protected ExternalApiClient $api) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Messaging
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Send a single text message.
     */
    public function sendText(string $number, string $message): array
    {
        return $this->api->request('POST', '/whatsapp/send', ['number' => $this->normalizeNumber($number), 'message' => $message], 30);
    }

    /**
     * Send a media message (image, video, audio, document).
     */
    public function sendMedia(string $number, string $mediaUrl, string $caption = ''): array
    {
        return $this->api->request('POST', '/whatsapp/send-media', ['number' => $this->normalizeNumber($number), 'media_url' => $mediaUrl, 'caption' => $caption], 30);
    }

    /**
     * Send bulk messages to multiple recipients (max 500 per call).
     * $recipients = [['number' => '234...', 'message' => '...'], ...]
     */
    public function sendBulk(array $recipients, string $defaultMessage = ''): array
    {
        $recipients = array_map(fn ($recipient) => [...$recipient, 'number' => $this->normalizeNumber($recipient['number'])], $recipients);

        return $this->api->request('POST', '/whatsapp/send-bulk', ['recipients' => $recipients, 'message' => $defaultMessage], 30);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Sessions
    // ─────────────────────────────────────────────────────────────────────────

    public function listSessions(): array
    {
        return $this->api->request('GET', '/whatsapp/sessions', [], 15);
    }

    public function createSession(string $sessionName, string $webhookUrl = ''): array
    {
        $payload = ['session_name' => $sessionName];
        if ($webhookUrl !== '') {
            $payload['webhook_url'] = $webhookUrl;
        }
        $session = $this->api->request('POST', '/whatsapp/sessions', $payload, 30);
        if (! is_string($session['id'] ?? null) || $session['id'] === '') {
            throw new ExternalIntegrationException('The messaging gateway did not create a usable session. Please retry shortly.', 502);
        }

        return $session;
    }

    public function getSession(string $sessionId): array
    {
        $session = $this->api->request('GET', '/whatsapp/sessions/'.rawurlencode($sessionId), [], 15);
        if (! in_array($session['status'] ?? null, ['CONNECTED', 'WAITING_FOR_QR', 'CONNECTING', 'DISCONNECTED', 'ERROR'], true)) {
            throw new ExternalIntegrationException('The messaging gateway did not return a valid session status.', 502);
        }

        return $session;
    }

    public function getQrCode(string $sessionId): array
    {
        return $this->api->request('GET', '/whatsapp/sessions/'.rawurlencode($sessionId).'/qr', [], 15);
    }

    public function logoutSession(string $sessionId): array
    {
        $result = $this->api->request('POST', '/whatsapp/sessions/'.rawurlencode($sessionId).'/logout', ['action' => 'logout'], 15);
        if (($result['status'] ?? null) !== 'DISCONNECTED') {
            throw new ExternalIntegrationException('The gateway has not confirmed disconnection. Refresh the connection status before retrying.', 502);
        }

        return $result;
    }

    public function gatewayStatus(): array
    {
        return $this->api->request('GET', '/whatsapp/status', [], 10);
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
            $number = '234'.substr($number, 1);
        }

        return $number;
    }
}
