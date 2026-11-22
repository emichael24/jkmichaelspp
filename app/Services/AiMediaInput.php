<?php

namespace App\Services;

use App\Exceptions\ExternalIntegrationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AiMediaInput
{
    /** Uploads are bounded and encoded in memory; they are never published locally. */
    public function fromRequest(Request $request, string $kind): array
    {
        if ($file = $request->file($kind)) {
            return [$kind.'_base64' => base64_encode($file->getContent())];
        }
        $url = $request->input($kind.'_url');
        // The provider fetches URLs server-side: reuse only its generated media.
        // Other sources must be uploaded as files, avoiding arbitrary URL fetches.
        try {
            if (! is_string($url) || ! str_starts_with($url, 'https://')) {
                throw new ExternalIntegrationException('Invalid media URL.');
            }
            $url = app(ExternalApiClient::class)->mediaUrl($url);
        } catch (ExternalIntegrationException) {
            throw ValidationException::withMessages([$kind.'_url' => 'Upload a file or use a generated media URL from the configured AI service.']);
        }

        return [$kind.'_url' => $url];
    }
}
