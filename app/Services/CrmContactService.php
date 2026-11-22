<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CrmContact;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CrmContactService
{
    public const TYPES = ['lead', 'prospect', 'customer', 'partner', 'student', 'alumni'];

    public const STATUSES = ['new', 'contacted', 'qualified', 'converted', 'lost', 'inactive'];

    public const CSV_COLUMNS = ['first_name', 'last_name', 'email', 'type', 'phone', 'whatsapp', 'company', 'job_title', 'source', 'address_line_1', 'address_line_2', 'city', 'state', 'postal_code', 'country', 'notes'];

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:100', 'last_name' => 'required|string|max:100',
            'email' => 'nullable|email:rfc|max:255', 'phone' => 'nullable|string|max:30', 'whatsapp' => 'nullable|string|max:30',
            'company' => 'nullable|string|max:255', 'job_title' => 'nullable|string|max:255',
            'type' => ['required', Rule::in(self::TYPES)], 'status' => ['required', Rule::in(self::STATUSES)],
            'marketing_email' => 'sometimes|boolean', 'marketing_whatsapp' => 'sometimes|boolean',
            'source' => 'nullable|string|max:100', 'notes' => 'nullable|string|max:5000',
            'address_line_1' => 'nullable|string|max:255', 'address_line_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100', 'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:30', 'country' => 'nullable|string|max:100',
        ];
    }

    public function save(array $input, ?CrmContact $contact = null, ?User $actor = null): CrmContact
    {
        $data = Validator::make($this->normalize($input), $this->rules())->validate();

        return DB::transaction(function () use ($data, $contact, $actor) {
            $this->lockWrites();
            $this->assertEmailAvailable($data['email'] ?? null, $contact?->id);
            if ($contact) {
                $contact = CrmContact::query()->lockForUpdate()->findOrFail($contact->id);
                $contact->update($data);
            } else {
                $contact = CrmContact::create($data + ['uuid' => (string) Str::uuid(), 'assigned_to' => $actor?->id ?? auth()->id()]);
            }

            return $contact;
        }, 3);
    }

    public function preview(UploadedFile $file, string $defaultType, User $actor): array
    {
        $handle = fopen($file->getRealPath(), 'rb');
        $rows = [];
        $errors = [];
        $emails = [];
        try {
            $headers = fgetcsv($handle, 0, ',', '"', '');
            if (! is_array($headers)) {
                throw ValidationException::withMessages(['file' => 'The CSV file is empty.']);
            }
            $headers = array_map(fn ($v) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $v ?? ''))), $headers);
            if (count($headers) !== count(array_unique($headers)) || array_diff($headers, self::CSV_COLUMNS) || array_diff(['first_name', 'last_name', 'email'], $headers)) {
                throw ValidationException::withMessages(['file' => 'Use the template headers, including first_name, last_name and email. Duplicate or unknown columns are not accepted.']);
            }
            $line = 1;
            while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $line++;
                if ($values === [null]) {
                    continue;
                }
                if (count($rows) + count($errors) >= 100) {
                    throw ValidationException::withMessages(['file' => 'Import at most 100 contacts per file. Split larger files before uploading.']);
                }
                if (count($values) !== count($headers)) {
                    $errors["Row {$line}"] = ['Column count does not match the header.'];

                    continue;
                }
                $data = $this->normalize(array_combine($headers, $values));
                $data['type'] = $data['type'] ?? $defaultType;
                $data['status'] = 'new';
                $validator = Validator::make($data, array_replace($this->rules(), ['email' => 'required|email:rfc|max:255']));
                if ($validator->fails()) {
                    $errors["Row {$line}"] = $validator->errors()->all();

                    continue;
                }
                if (isset($emails[$data['email']]) || $this->emailExists($data['email'])) {
                    $errors["Row {$line}"] = ['This email already exists in the CRM (including archived contacts) or appears twice in this file. Existing contacts are never overwritten.'];

                    continue;
                }
                $emails[$data['email']] = true;
                $rows[] = $validator->validated();
            }
        } finally {
            fclose($handle);
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        if (! count($rows)) {
            throw ValidationException::withMessages(['file' => 'Add at least one contact row below the header.']);
        }
        $payload = ['id' => (string) Str::uuid(), 'actor' => $actor->id, 'expires' => now()->addMinutes(15)->timestamp, 'rows' => $rows];

        return ['rows' => $rows, 'token' => Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR)), 'expires_at' => now()->addMinutes(15)->toIso8601String()];
    }

    public function import(string $token, User $actor): int
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['token' => 'The preview is invalid. Upload the CSV again.']);
        }
        if (($payload['actor'] ?? null) !== $actor->id || ($payload['expires'] ?? 0) <= now()->timestamp || ! count($payload['rows'] ?? [])) {
            throw ValidationException::withMessages(['token' => 'The preview expired or belongs to another administrator. Upload the CSV again.']);
        }

        return DB::transaction(function () use ($payload, $actor) {
            $this->lockWrites();
            $receipt = DB::table('crm_contact_imports')->where('id', $payload['id'])->first();
            if ($receipt) {
                return (int) $receipt->row_count;
            }
            foreach ($payload['rows'] as $index => $row) {
                $this->assertEmailAvailable($row['email'], field: 'token');
                CrmContact::create($row + ['uuid' => (string) Str::uuid(), 'assigned_to' => $actor->id]);
            }
            $count = count($payload['rows']);
            DB::table('crm_contact_imports')->insert([
                'id' => $payload['id'], 'user_id' => $actor->id, 'row_count' => $count,
                'content_hash' => hash('sha256', json_encode($payload['rows'], JSON_THROW_ON_ERROR)), 'created_at' => now(),
            ]);
            AuditLog::log('crm_import', CrmContact::class, 0, null, ['batch_id' => $payload['id'], 'row_count' => $count]);

            return $count;
        }, 3);
    }

    public function normalize(array $input): array
    {
        foreach ($input as $key => $value) {
            if (is_string($value)) {
                if (! mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0")) {
                    throw ValidationException::withMessages(['file' => 'Use a UTF-8 CSV file without binary data.']);
                }
                $input[$key] = trim($value) === '' ? null : trim($value);
            }
        }
        foreach (['email', 'type', 'status'] as $key) {
            if (is_string($input[$key] ?? null)) {
                $input[$key] = strtolower($input[$key]);
            }
        }

        return $input;
    }

    public function emailExists(string $email, ?int $except = null): bool
    {
        return CrmContact::withTrashed()->where('email_normalized', $email)->when($except, fn ($q) => $q->where('id', '!=', $except))->exists();
    }

    private function assertEmailAvailable(?string $email, ?int $except = null, string $field = 'email'): void
    {
        if ($email && $this->emailExists($email, $except)) {
            throw ValidationException::withMessages([$field => 'This email already exists in the CRM, including archived contacts. Refresh the preview or edit the existing contact.']);
        }
    }

    private function lockWrites(): void
    {
        DB::table('crm_contact_write_locks')->where('id', 1)->lockForUpdate()->firstOrFail();
    }
}
