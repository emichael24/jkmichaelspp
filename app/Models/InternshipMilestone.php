<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternshipMilestone extends Model
{
    protected $fillable = [
        'track_id',
        'title',
        'description',
        'week_number',
        'deliverables',
        'sort_order',
    ];

    protected $casts = [
        'deliverables' => 'array',
    ];

    /* ── Relationships ─────────────────────────────── */

    public function track(): BelongsTo
    {
        return $this->belongsTo(InternshipTrack::class, 'track_id');
    }
}
