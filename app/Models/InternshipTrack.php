<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InternshipTrack extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'description',
        'duration_weeks',
        'price',
        'currency',
        'orientation_course_id',
        'tools_required',
        'templates',
        'expectations',
        'skills_covered',
        'max_participants',
        'is_published',
        'review_rubric',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'tools_required' => 'array',
        'templates' => 'array',
        'skills_covered' => 'array',
        'is_published' => 'boolean',
        'review_rubric' => 'array',
    ];

    /* ── Relationships ─────────────────────────────── */

    public function orientationCourse(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'orientation_course_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(InternshipEnrollment::class, 'track_id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(InternshipMilestone::class, 'track_id')->orderBy('sort_order');
    }

    /* ── Scopes ────────────────────────────────────── */

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /* ── Helpers ───────────────────────────────────── */

    public function activeEnrollmentCount(): int
    {
        return $this->enrollments()->whereIn('status', ['enrolled', 'active'])->count();
    }

    public function isFull(): bool
    {
        return $this->activeEnrollmentCount() >= $this->max_participants;
    }

    public function reviewRubric(): array
    {
        return $this->review_rubric ?: ['Quality', 'Timeliness', 'Communication', 'Initiative', 'Technical skill'];
    }
}
