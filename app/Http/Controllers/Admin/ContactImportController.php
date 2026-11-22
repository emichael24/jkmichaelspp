<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessContactImport;
use App\Models\CrmContactImport;
use App\Services\BackgroundContactImportService;
use App\Services\CrmContactService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContactImportController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'imports' => CrmContactImport::where('user_id', $request->user()->id)->latest()->limit(20)->get()->map->result(),
            'max_file_mb' => config('crm.import_max_kb') / 1024,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, BackgroundContactImportService $service)
    {
        $data = $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:'.config('crm.import_max_kb'),
            'type' => ['required', Rule::in(CrmContactService::TYPES)], 'request_id' => 'required|uuid',
        ]);
        $batch = $service->upload($data['file'], $data['type'], $data['request_id'], $request->user());

        return response()->json(['import' => $batch->result()], 202)->header('Cache-Control', 'private, no-store');
    }

    public function retry(Request $request, string $id)
    {
        $batch = DB::transaction(function () use ($request, $id) {
            $batch = $this->owned($request, $id, true);
            if ($batch->status !== 'failed') {
                return $batch;
            }
            if (! Storage::disk('local')->exists($batch->path)) {
                throw ValidationException::withMessages(['file' => 'The retained source has expired. Upload the CSV again; existing contacts will be skipped.']);
            }
            $batch->update(['status' => 'queued', 'error_message' => null, 'finished_at' => null]);
            ProcessContactImport::dispatch($batch->id, $batch->byte_offset);

            return $batch;
        });

        return response()->json(['import' => $batch->result()]);
    }

    public function cancel(Request $request, string $id)
    {
        $batch = DB::transaction(function () use ($request, $id) {
            $batch = $this->owned($request, $id, true);
            if (in_array($batch->status, ['queued', 'processing', 'failed'])) {
                $batch->update(['status' => 'cancelled', 'finished_at' => now()]);
            }

            return $batch;
        });
        if ($batch->status === 'cancelled') {
            Storage::disk('local')->delete($batch->path);
        }

        return response()->json(['import' => $batch->result()]);
    }

    public function errors(Request $request, string $id)
    {
        $batch = $this->owned($request, $id);

        return response()->streamDownload(function () use ($batch) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Record (including header)', 'Result', 'Reason'], ',', '"', '');
            foreach (DB::table('crm_contact_import_errors')->where('import_id', $batch->id)->orderBy('record_number')->cursor() as $row) {
                fputcsv($output, [$row->record_number, $row->kind, $row->message], ',', '"', '');
            }
            fclose($output);
        }, 'contact-import-errors-'.$batch->id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    private function owned(Request $request, string $id, bool $lock = false): CrmContactImport
    {
        return CrmContactImport::where('user_id', $request->user()->id)->when($lock, fn ($query) => $query->lockForUpdate())->findOrFail($id);
    }
}
