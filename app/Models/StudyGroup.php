<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class StudyGroup extends Model
{
    protected $fillable = [
        'organization_id',
        'name',
        'course_id',
        'created_by',
        'max_members',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'study_group_members')
            ->withPivot('joined_at');
    }

    public function isFull(): bool
    {
        return $this->members()->count() >= $this->max_members;
    }

    public function hasMember(int $userId): bool
    {
        return $this->members()->where('user_id', $userId)->exists();
    }

    public function addMember(int $userId): bool
    {
        if ($this->isFull() || $this->hasMember($userId)) {
            return false;
        }

        $this->members()->attach($userId, ['joined_at' => now()]);

        return true;
    }

    public function removeMember(int $userId): void
    {
        $this->members()->detach($userId);
    }

    public function getMemberCount(): int
    {
        return $this->members()->count();
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeForCourse($query, $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_public', true)
            ->withCount('members')
            ->havingRaw('members_count < max_members');
    }
}
