<?php

namespace App\Jobs;

use App\Models\CrmContactImport;
use App\Services\BackgroundContactImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessContactImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 90;

    public int $backoff = 15;

    public function __construct(public string $importId, public int $offset)
    {
        $this->onConnection('crm_imports')->onQueue('contact-imports');
    }

    public function handle(BackgroundContactImportService $service): void
    {
        $service->process($this->importId, $this->offset);
    }

    public function failed(?\Throwable $error): void
    {
        CrmContactImport::whereKey($this->importId)->where('byte_offset', $this->offset)->whereIn('status', ['queued', 'processing'])->update([
            'status' => 'failed', 'error_message' => 'Processing stopped. Imported contacts are saved; retry resumes from the last checkpoint.', 'finished_at' => now(),
        ]);
    }
}
