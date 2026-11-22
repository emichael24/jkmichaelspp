<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Forum extends Model
{
    protected $fillable = [
        'organization_id',
        'course_id',
        'cohort_id',
        'title',
        'description',
        'is_general',
        'sort_order',
    ];

    protected $casts = [
        'is_general' => 'boolean',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    public function threads(): HasMany
    {
        return $this->hasMany(ForumThread::class);
    }

    public function scopeGeneral($query)
    {
        return $query->where('is_general', true);
    }

    public function scopeForCourse($query, $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    public function scopeForCohort($query, $cohortId)
    {
        return $query->where('cohort_id', $cohortId);
    }

    public function getThreadCount(): int
    {
        return $this->threads()->count();
    }
}
