<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    protected $fillable = [
        'cohort_id',
        'course_id',
        'lesson_id',
        'title',
        'description',
        'instructions',
        'rubric',
        'max_score',
        'due_date',
        'allow_late',
        'max_resubmissions',
        'created_by',
        'is_published',
        'prompts',
        'passing_score',
        'allow_file_upload',
    ];

    protected $casts = [
        'rubric' => 'array',
        'prompts' => 'array',
        'allow_file_upload' => 'boolean',
        'max_score' => 'integer',
        'passing_score' => 'integer',
        'due_date' => 'datetime',
        'allow_late' => 'boolean',
        'is_published' => 'boolean',
    ];

    /* ── Relationships ─────────────────────────────── */

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    /* ── Scopes ────────────────────────────────────── */

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeForCohort($query, int $cohortId)
    {
        return $query->where('cohort_id', $cohortId);
    }

    /* ── Helpers ───────────────────────────────────── */

    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isPast();
    }
}
