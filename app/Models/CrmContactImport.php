<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmContactImport extends Model
{
    protected $table = 'crm_contact_import_batches';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['path', 'content_hash', 'headers', 'user_id'];

    protected $casts = [
        'headers' => 'array', 'finished_at' => 'datetime', 'user_id' => 'integer',
        'file_size' => 'integer', 'byte_offset' => 'integer', 'record_number' => 'integer',
        'processed_count' => 'integer', 'imported_count' => 'integer', 'skipped_count' => 'integer', 'invalid_count' => 'integer',
    ];

    public function result(): array
    {
        return $this->only(['id', 'filename', 'default_type', 'status', 'file_size', 'processed_count', 'imported_count', 'skipped_count', 'invalid_count', 'error_message', 'created_at', 'finished_at']) + [
            'progress' => in_array($this->status, ['completed', 'completed_with_errors']) ? 100 : min(99, (int) floor(100 * $this->byte_offset / max(1, $this->file_size))),
        ];
    }
}
