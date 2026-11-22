<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseRating extends Model
{
    protected $fillable = [
        'user_id',
        'course_id',
        'rating',
        'review',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get average rating for a course.
     */
    public static function averageForCourse(int $courseId): ?float
    {
        $avg = self::where('course_id', $courseId)->avg('rating');

        return $avg ? round($avg, 1) : null;
    }

    /**
     * Get rating distribution for a course.
     */
    public static function distributionForCourse(int $courseId): array
    {
        $distribution = [];
        for ($i = 1; $i <= 5; $i++) {
            $distribution[$i] = self::where('course_id', $courseId)->where('rating', $i)->count();
        }

        return $distribution;
    }

    /**
     * Check if a user has already rated a course.
     */
    public static function hasRated(int $userId, int $courseId): bool
    {
        return self::where('user_id', $userId)->where('course_id', $courseId)->exists();
    }
}
