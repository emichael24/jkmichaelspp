<?php

namespace App\Services;

use App\Exceptions\ExternalIntegrationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExternalApiClient
{
    public function request(string $method, string $path, array $payload = [], int $timeout = 90): array
    {
        $key = trim((string) config('services.flexichurch.api_key'));
        $base = rtrim((string) config('services.flexichurch.base_url'), '/');
        if ($key === '' || parse_url($base, PHP_URL_SCHEME) !== 'https') {
            throw new ExternalIntegrationException('The AI and WhatsApp integration is not configured. Contact your platform administrator.');
        }
        try {
            $client = Http::withToken($key)->acceptJson()->baseUrl($base)->withoutRedirecting()->connectTimeout(10)->timeout($timeout);
            $response = $method === 'GET' ? $client->get($path, $payload) : $client->post($path, $payload);
        } catch (ConnectionException) {
            throw new ExternalIntegrationException('The integration service could not be reached. Please retry shortly.');
        }
        $body = $response->json();
        if (! $response->successful() || ! is_array($body) || ($body['status'] ?? null) !== 'success') {
            Log::warning('External integration request failed', ['endpoint' => strtok($path, '?'), 'http_status' => $response->status()]);
            [$message, $status] = match ($response->status()) {
                401, 403 => ['The integration key is invalid, expired, or missing the required permission. Contact your platform administrator.', 503],
                404 => ['The requested integration session or feature is unavailable.', 404],
                422 => ['The integration service rejected the input. Check the selected options and file requirements.', 422],
                429 => ['The integration service is busy. Wait a minute before trying again.', 429],
                default => ['The integration service could not complete the request. Please retry shortly.', 503],
            };
            throw new ExternalIntegrationException($message, $status);
        }
        if (! is_array($body['data'] ?? null)) {
            throw new ExternalIntegrationException('The integration service returned an incomplete response.', 502);
        }

        return $body['data'];
    }

    public function mediaUrl(string $path): string
    {
        $base = parse_url((string) config('services.flexichurch.base_url'));
        $origin = 'https://'.($base['host'] ?? '').(isset($base['port']) ? ':'.$base['port'] : '');
        $mediaBase = rtrim((string) config('services.flexichurch.media_base_url', $origin.'/storage'), '/') ?: $origin.'/storage';
        if ($path === '' || str_contains($path, '\\') || preg_match('/[\x00-\x20]/', $path)) {
            throw new ExternalIntegrationException('The integration service did not return a usable media file.', 502);
        }
        if (str_starts_with($path, 'https://')) {
            foreach (array_unique([$origin.'/storage/', $mediaBase.'/']) as $prefix) {
                if (str_starts_with($prefix, 'https://') && str_starts_with($path, $prefix)) {
                    $relative = substr($path, strlen($prefix));
                    if (preg_match('~^media/[a-zA-Z0-9_/-]+\.[a-zA-Z0-9]+$~D', $relative)) {
                        return $path;
                    }
                }
            }
            throw new ExternalIntegrationException('The integration service returned an unexpected media location.', 502);
        }
        $path = ltrim($path, '/');
        if (! preg_match('~^(?:storage/)?media/[a-zA-Z0-9_./-]+$~', $path) || in_array('..', explode('/', $path), true)) {
            throw new ExternalIntegrationException('The integration service returned an invalid media location.', 502);
        }

        $relative = str_starts_with($path, 'storage/') ? substr($path, 8) : $path;

        return $this->mediaUrl($mediaBase.'/'.$relative);
    }
}
