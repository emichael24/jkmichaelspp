<?php

namespace App\Jobs;

use App\Models\MediaAsset;
use App\Services\ProtectedDocumentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Converts a protected document (PPTX to PDF, page count) and renders its first pages.
 * Runs in the queue worker because the web server's PHP-FPM service may not create the
 * namespaces the converter sandbox needs.
 */
class PrepareDocument implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 115;

    public int $uniqueFor = 150;

    public function __construct(public string $assetId) {}

    public function uniqueId(): string
    {
        return $this->assetId;
    }

    public function handle(ProtectedDocumentService $documents): void
    {
        $asset = MediaAsset::find($this->assetId);
        if (! $asset || ! $documents->supports($asset)) {
            return;
        }
        try {
            $metadata = $documents->prepareMetadata($asset);
        } catch (HttpExceptionInterface $exception) {
            // Readers fall back to the browser renderer instead of waiting on a failed conversion.
            $documents->recordFailure($asset, $exception->getMessage() ?: 'The document could not be prepared.');
            report(new \RuntimeException('Document preparation failed for media '.$asset->id.': '.$exception->getMessage()));

            return;
        }
        (new RenderDocumentPages($asset->id, 1, min($metadata['pages'], 1 + ProtectedDocumentService::PREFETCH)))->handle($documents);
    }
}
