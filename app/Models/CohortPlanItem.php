<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CohortPlanItem extends Model
{
    protected $fillable = [
        'weekly_plan_id',
        'itemable_type',
        'itemable_id',
        'sort_order',
        'is_mandatory',
        'due_date',
    ];

    protected $casts = [
        'is_mandatory' => 'boolean',
        'due_date'     => 'date',
    ];

    /* ── relationships ── */
    public function weeklyPlan(): BelongsTo
    {
        return $this->belongsTo(CohortWeeklyPlan::class, 'weekly_plan_id');
    }

    public function itemable(): MorphTo
    {
        return $this->morphTo();
    }
}
