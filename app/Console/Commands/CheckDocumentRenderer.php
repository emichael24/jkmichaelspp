<?php

namespace App\Console\Commands;

use App\Services\ProtectedDocumentService;
use Illuminate\Console\Command;

class CheckDocumentRenderer extends Command
{
    protected $signature = 'documents:check';

    protected $description = 'Report which PDF/PowerPoint lesson renderer this server will use';

    public function handle(ProtectedDocumentService $documents): int
    {
        $report = $documents->diagnostics();
        $this->table(['Requirement', 'Status'], collect($report)->map(fn ($value, $key) => [$key, is_bool($value) ? ($value ? 'OK' : 'missing') : $value])->values()->all());
        $pdf = $report['gd'] && $report['pdfinfo'] && $report['pdftoppm'];
        $this->line('PDF lessons:        '.($pdf ? 'server-rendered, watermarked pages' : 'rendered in the browser (install poppler-utils + php-gd for server rendering)'));
        $this->line('PowerPoint lessons: '.($pdf && $report['libreoffice'] ? 'converted on the server (full fidelity)' : 'rendered in the browser (install LibreOffice Impress for best fidelity)'));
        if ($report['renderer'] === 'server' && ! ($pdf && $report['libreoffice'])) {
            $this->error('DOCUMENT_RENDERER=server but converters are missing: document lessons will fail. Use "auto" or install the tools.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
