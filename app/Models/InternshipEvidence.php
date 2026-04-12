<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternshipEvidence extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected $table = 'internship_evidence';

    /* ── Relationships ─────────────────────────────── */

    public function submission(): BelongsTo
    {
        return $this->belongsTo(InternshipSubmission::class, 'submission_id');
    }
}
