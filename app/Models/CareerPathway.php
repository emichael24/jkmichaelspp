<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class CareerPathway extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'description',
        'skills_required',
    ];

    protected $casts = [
        'skills_required' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (CareerPathway $pathway) {
            if (empty($pathway->slug)) {
                $pathway->slug = Str::slug($pathway->title);
            }
        });
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'career_pathway_courses', 'pathway_id', 'course_id')
            ->withPivot(['sort_order', 'is_required'])
            ->orderByPivot('sort_order');
    }

    public function requiredCourses(): BelongsToMany
    {
        return $this->courses()->wherePivot('is_required', true);
    }

    public function optionalCourses(): BelongsToMany
    {
        return $this->courses()->wherePivot('is_required', false);
    }

    /**
     * Calculate user's progress along this pathway.
     */
    public function getProgressForUser(int $userId): array
    {
        $requiredCourses = $this->requiredCourses()->get();
        $totalRequired = $requiredCourses->count();

        if ($totalRequired === 0) {
            return ['completed' => 0, 'total' => 0, 'percentage' => 100];
        }

        $completedCount = 0;
        foreach ($requiredCourses as $course) {
            $enrollment = CourseEnrollment::where('user_id', $userId)
                ->where('course_id', $course->id)
                ->where('status', 'completed')
                ->first();

            if ($enrollment) {
                $completedCount++;
            }
        }

        return [
            'completed' => $completedCount,
            'total' => $totalRequired,
            'percentage' => round(($completedCount / $totalRequired) * 100),
        ];
    }

    public function getNextCourseForUser(int $userId): ?Course
    {
        $courses = $this->requiredCourses()->get();

        foreach ($courses as $course) {
            $enrollment = CourseEnrollment::where('user_id', $userId)
                ->where('course_id', $course->id)
                ->where('status', 'completed')
                ->first();

            if (!$enrollment) {
                return $course;
            }
        }

        return null;
    }
}
