<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CohortWeeklyPlan extends Model
{
    protected $fillable = [
        'cohort_id',
        'week_number',
        'title',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    /* ── relationships ── */
    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CohortPlanItem::class, 'weekly_plan_id')->orderBy('sort_order');
    }

    /* ── helpers ── */
    public function isCurrent(): bool
    {
        $today = now()->toDateString();
        return $this->start_date->toDateString() <= $today
            && $this->end_date->toDateString() >= $today;
    }
}
