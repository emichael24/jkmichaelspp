<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Cohort extends Model
{
    protected $fillable = [
        'uuid',
        'course_id',
        'organization_id',
        'name',
        'description',
        'facilitator_id',
        'start_date',
        'end_date',
        'max_learners',
        'status',
        'settings',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'settings' => 'array',
    ];

    /* ── boot ── */
    protected static function booted(): void
    {
        static::creating(function (self $cohort) {
            $cohort->uuid = $cohort->uuid ?: (string) Str::uuid();
        });
    }

    /* ── relationships ── */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function facilitator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'facilitator_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(CohortMember::class);
    }

    public function learners(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'cohort_members')
            ->wherePivot('role', 'learner')
            ->wherePivot('status', 'active')
            ->withPivot('role', 'status', 'joined_at')
            ->withTimestamps();
    }

    public function weeklyPlans(): HasMany
    {
        return $this->hasMany(CohortWeeklyPlan::class)->orderBy('week_number');
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }

    public function interventions(): HasMany
    {
        return $this->hasMany(Intervention::class);
    }

    /* ── helpers ── */
    public function activeMemberCount(): int
    {
        return $this->members()->where('status', 'active')->count();
    }

    public function isFull(): bool
    {
        return $this->activeMemberCount() >= $this->max_learners;
    }

    /* ── scopes ── */
    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    public function scopeForFacilitator($q, int $userId)
    {
        return $q->where('facilitator_id', $userId);
    }

    public function scopeForOrganization($q, int $orgId)
    {
        return $q->where('organization_id', $orgId);
    }
}
