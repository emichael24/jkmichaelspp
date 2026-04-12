<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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

    protected static function booted(): void
    {
        static::creating(function (Lesson $lesson) {
            if (empty($lesson->slug)) {
                $lesson->slug = Str::slug($lesson->title);
            }
        });
    }
}
