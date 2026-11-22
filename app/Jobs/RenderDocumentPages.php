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

/** Rasterizes a short run of document pages into the private page cache. */
class RenderDocumentPages implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 115;

    public int $uniqueFor = 120;

    public function __construct(public string $assetId, public int $from, public int $to) {}

    public function uniqueId(): string
    {
        return $this->assetId.':'.$this->from.'-'.$this->to;
    }

    public function handle(ProtectedDocumentService $documents): void
    {
        $asset = MediaAsset::find($this->assetId);
        if (! $asset || ! $documents->supports($asset)) {
            return;
        }
        $started = microtime(true);
        for ($page = max(1, $this->from); $page <= $this->to; $page++) {
            if ($documents->pageCached($asset, $page)) {
                continue;
            }
            // Stay well inside the worker's 120-second job timeout; readers re-request missing pages.
            if (microtime(true) - $started > 90) {
                return;
            }
            try {
                $documents->renderPage($asset, $page);
            } catch (HttpExceptionInterface $exception) {
                if ($exception->getStatusCode() !== 409) {
                    report(new \RuntimeException('Rendering page '.$page.' of media '.$asset->id.' failed: '.$exception->getMessage()));
                }

                return;
            }
        }
    }
}
