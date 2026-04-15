<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\CarbonInterface;
use Illuminate\Support\Str;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_id',
        'title',
        'slug',
        'content_type',
        'text_content',
        'summary',
        'key_terms',
        'video_url',
        'video_provider',
        'scorm_path',
        'scorm_version',
        'video_duration_seconds',
        'video_transcript',
        'video_chapters',
        'audio_url',
        'audio_duration_seconds',
        'pdf_url',
        'slides_url',
        'sort_order',
        'duration_minutes',
        'is_free_preview',
        'is_published',
        'is_downloadable',
        'unlock_type',
        'unlock_at',
        'unlock_days_after_enrollment',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'key_terms'              => 'array',
            'video_chapters'         => 'array',
            'sort_order'             => 'integer',
            'duration_minutes'       => 'integer',
            'video_duration_seconds' => 'integer',
            'audio_duration_seconds' => 'integer',
            'is_free_preview'        => 'boolean',
            'is_published'           => 'boolean',
            'is_downloadable'        => 'boolean',
            'unlock_at'              => 'datetime',
            'unlock_days_after_enrollment' => 'integer',
            'version'                => 'integer',
        ];
    }

    public function videoQuizzes()
    {
        return $this->hasMany(LessonVideoQuiz::class)->orderBy('timestamp_seconds');
    }

    public function scormProgress()
    {
        return $this->hasMany(ScormProgress::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function course()
    {
        return $this->hasOneThrough(
            Course::class,
            Module::class,
            'id',       // modules.id
            'id',       // courses.id
            'module_id', // lessons.module_id
            'course_id'  // modules.course_id
        );
    }

    public function progress()
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function notes()
    {
        return $this->hasMany(UserNote::class);
    }

    public function bookmarks()
    {
        return $this->morphMany(Bookmark::class, 'bookmarkable');
    }

    public function scopePublished($query)
    {
        return $query->where('lessons.is_published', true);
    }

    public function progressFor(User $user): ?LessonProgress
    {
        return $this->progress()->where('user_id', $user->id)->first();
    }

    public function availableFrom(?CourseEnrollment $enrollment = null): ?CarbonInterface
    {
        return match ($this->unlock_type ?: 'immediate') {
            'date' => $this->unlock_at,
            'days_after_enrollment' => $enrollment?->enrolled_at?->copy()->addDays($this->unlock_days_after_enrollment ?? 0),
            default => null,
        };
    }

    public function isUnlockedFor(?CourseEnrollment $enrollment = null): bool
    {
        $unlockType = $this->unlock_type ?: 'immediate';

        if ($unlockType === 'immediate') {
            return true;
        }

        $availableFrom = $this->availableFrom($enrollment);

        return $availableFrom !== null && now()->greaterThanOrEqualTo($availableFrom);
    }

    public function availabilityLabel(?CourseEnrollment $enrollment = null): ?string
    {
        if ($this->isUnlockedFor($enrollment)) {
            return null;
        }

        $availableFrom = $this->availableFrom($enrollment);

        if ($availableFrom === null) {
            return $this->unlock_type === 'days_after_enrollment'
                ? 'Available after enrollment'
                : 'Availability to be announced';
        }

        return 'Available ' . $availableFrom->diffForHumans(now(), [
            'syntax' => CarbonInterface::DIFF_RELATIVE_TO_NOW,
        ]);
    }

    protected static function booted(): void
    {
        static::creating(function (Lesson $lesson) {
            if (empty($lesson->slug)) {
                $lesson->slug = Str::slug($lesson->title);
            }
        });
    }
}
