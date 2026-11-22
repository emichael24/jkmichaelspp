<?php

namespace App\Services;

use App\Jobs\ProcessContactImport;
use App\Models\AuditLog;
use App\Models\CrmContactImport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BackgroundContactImportService
{
    public function upload(UploadedFile $file, string $type, string $id, User $actor): CrmContactImport
    {
        $hash = hash_file('sha256', $file->getRealPath());

        return DB::transaction(function () use ($file, $type, $id, $actor, $hash) {
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            if ($existing = CrmContactImport::find($id)) {
                abort_unless($existing->user_id === $actor->id, 404);
                if ($existing->content_hash !== $hash || $existing->default_type !== $type) {
                    throw ValidationException::withMessages(['file' => 'This upload reference belongs to a different file. Choose the file again.']);
                }

                return $existing;
            }
            if (CrmContactImport::where('user_id', $actor->id)->whereIn('status', ['queued', 'processing'])->count() >= 3) {
                throw ValidationException::withMessages(['file' => 'Three imports are already waiting or running. Let one finish, or cancel one, before uploading another.']);
            }
            $handle = fopen($file->getRealPath(), 'rb');
            try {
                $header = app(ContactCsvReader::class)->read($handle);
                $headers = $header['values'] ?? null;
                if (! is_array($headers) || $header['oversized'] || $header['malformed']) {
                    throw ValidationException::withMessages(['file' => 'The CSV file is empty or its header is too large.']);
                }
                $headers = array_map(fn ($value) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $value ?? ''))), $headers);
                if (count($headers) !== count(array_unique($headers)) || array_diff($headers, CrmContactService::CSV_COLUMNS) || array_diff(['first_name', 'last_name', 'email'], $headers)) {
                    throw ValidationException::withMessages(['file' => 'Use the template headers, including first_name, last_name and email. Duplicate or unknown columns are not accepted.']);
                }
                $offset = ftell($handle);
            } finally {
                fclose($handle);
            }
            $path = $file->storeAs('contact-imports', $id.'.csv', 'local');
            if (! $path) {
                throw ValidationException::withMessages(['file' => 'The upload could not be stored. Please try again.']);
            }
            try {
                $batch = CrmContactImport::create([
                    'id' => $id, 'user_id' => $actor->id, 'filename' => mb_substr(basename($file->getClientOriginalName()), 0, 240),
                    'path' => $path, 'content_hash' => $hash, 'headers' => $headers, 'default_type' => $type,
                    'file_size' => $file->getSize(), 'byte_offset' => $offset,
                ]);
                ProcessContactImport::dispatch($id, $offset);
                AuditLog::log('crm_import_queued', CrmContactImport::class, 0, null, ['batch_id' => $id], $actor->id);

                return $batch->fresh();
            } catch (\Throwable $error) {
                Storage::disk('local')->delete($path);
                throw $error;
            }
        }, 3);
    }

    public function process(string $id, int $expectedOffset): void
    {
        $finishedPath = DB::transaction(function () use ($id, $expectedOffset) {
            $batch = CrmContactImport::lockForUpdate()->findOrFail($id);
            if (! in_array($batch->status, ['queued', 'processing']) || $batch->byte_offset !== $expectedOffset) {
                return null;
            }
            $actor = User::find($batch->user_id);
            if (! $actor?->is_active || ! $actor->hasRole('platform_admin')) {
                $batch->update(['status' => 'cancelled', 'error_message' => 'The importing administrator no longer has permission.', 'finished_at' => now()]);

                return $batch->path;
            }
            $handle = Storage::disk('local')->readStream($batch->path);
            if (! is_resource($handle)) {
                throw new \RuntimeException('Contact import source is unavailable.');
            }
            $service = app(CrmContactService::class);
            // All CRM contact writers use this lock. It also protects duplicate checks across imports.
            DB::table('crm_contact_write_locks')->where('id', 1)->lockForUpdate()->firstOrFail();
            try {
                if (fseek($handle, $batch->byte_offset) !== 0) {
                    throw new \RuntimeException('Contact import checkpoint is unavailable.');
                }
                $done = false;
                for ($i = 0; $i < config('crm.import_batch_size'); $i++) {
                    $record = app(ContactCsvReader::class)->read($handle);
                    if ($record === null) {
                        $done = true;
                        break;
                    }
                    $batch->record_number++;
                    $batch->byte_offset = ftell($handle);
                    $values = $record['values'];
                    if ($values === [null]) {
                        continue;
                    }
                    $batch->processed_count++;
                    try {
                        if ($record['malformed']) {
                            throw ValidationException::withMessages(['row' => 'The CSV record has an unclosed quoted field.']);
                        }
                        if ($record['oversized'] || count($values) !== count($batch->headers)) {
                            throw ValidationException::withMessages(['row' => 'The record exceeds 64 KB or its column count differs from the header.']);
                        }
                        $data = $service->normalize(array_combine($batch->headers, $values));
                        $data['type'] = $data['type'] ?? $batch->default_type;
                        $data['status'] = 'new';
                        $data = Validator::make($data, array_replace($service->rules(), ['email' => 'required|email:rfc|max:255']))->validate();
                        if ($service->emailExists($data['email'])) {
                            $this->rowError($batch, 'duplicate', 'Email already exists, is archived, or appeared earlier in this file. Existing contacts were preserved.');
                            $batch->skipped_count++;

                            continue;
                        }
                        $service->save($data, actor: $actor);
                        $batch->imported_count++;
                    } catch (ValidationException $error) {
                        $this->rowError($batch, 'invalid', implode(' ', array_merge(...array_values($error->errors()))));
                        $batch->invalid_count++;
                    }
                }
                $done = $done || $batch->byte_offset >= $batch->file_size;
                $batch->status = $done ? ($batch->skipped_count + $batch->invalid_count > 0 ? 'completed_with_errors' : 'completed') : 'processing';
                $batch->finished_at = $done ? now() : null;
                $batch->error_message = null;
                $batch->save();
                AuditLog::log($done ? 'crm_import_completed' : 'crm_import_progress', CrmContactImport::class, 0, null,
                    ['batch_id' => $id, 'imported_count' => $batch->imported_count, 'skipped_count' => $batch->skipped_count, 'invalid_count' => $batch->invalid_count], $actor->id);
                if (! $done) {
                    ProcessContactImport::dispatch($id, $batch->byte_offset);
                }

                return $done ? $batch->path : null;
            } finally {
                fclose($handle);
            }
        }, 3);
        if ($finishedPath) {
            Storage::disk('local')->delete($finishedPath);
        }
    }

    private function rowError(CrmContactImport $batch, string $kind, string $message): void
    {
        DB::table('crm_contact_import_errors')->insert([
            'import_id' => $batch->id, 'record_number' => $batch->record_number, 'kind' => $kind, 'message' => mb_substr($message, 0, 2000),
        ]);
    }
}
