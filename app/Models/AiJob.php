<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiJob extends Model
{
    protected $fillable = [
        'uuid', 'user_id', 'job_type', 'status', 'input_payload',
        'output', 'output_file_path', 'error_message', 'tokens_used',
        'processing_seconds', 'completed_at',
    ];

    protected $casts = [
        'input_payload' => 'array',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
