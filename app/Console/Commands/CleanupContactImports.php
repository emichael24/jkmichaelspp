<?php

namespace App\Console\Commands;

use App\Models\CrmContactImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CleanupContactImports extends Command
{
    protected $signature = 'crm:cleanup-imports';

    protected $description = 'Remove retained CSV sources after seven days; keep import results and checkpoints';

    public function handle(): int
    {
        $removed = 0;
        CrmContactImport::whereNotIn('status', ['queued', 'processing'])->where('finished_at', '<', now()->subDays(config('crm.import_source_retention_days')))
            ->orderBy('id')->chunkById(100, function ($batches) use (&$removed) {
                foreach ($batches as $batch) {
                    DB::transaction(function () use ($batch, &$removed) {
                        $current = CrmContactImport::lockForUpdate()->find($batch->id);
                        if ($current && ! in_array($current->status, ['queued', 'processing']) && Storage::disk('local')->exists($current->path)) {
                            $removed += Storage::disk('local')->delete($current->path) ? 1 : 0;
                        }
                    });
                }
            });
        $this->info("Removed {$removed} retained contact-import sources.");

        return self::SUCCESS;
    }
}
