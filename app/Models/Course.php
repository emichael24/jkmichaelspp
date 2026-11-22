<?php

namespace App\Models;

use App\Models\Traits\RecordsDomainAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Course extends Model
{
    use HasFactory, SoftDeletes;
    use RecordsDomainAudit;

    protected $fillable = [
        'uuid', 'revision_of_id', 'superseded_at',
        'category_id',
        'title',
        'slug',
        'subtitle',
        'description',
        'thumbnail',
        'preview_video',
        'level',
        'type',
        'duration_hours',
        'price',
        'currency',
        'discount_price',
        'exam_blueprint',
        'prerequisites',
        'learning_outcomes',
        'tags',
        'is_published',
        'published_at',
        'version',
        'author_id',
        'reviewer_id',
        'created_by_role',
        'approval_status',
        'submitted_for_review_at',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'total_enrollments',
        'avg_rating',
        'sequential_lessons',
    ];

    protected function casts(): array
    {
        return [
            'exam_blueprint' => 'array',
            'prerequisites' => 'array',
            'learning_outcomes' => 'array',
            'tags' => 'array',
            'is_published' => 'boolean',
            'sequential_lessons' => 'boolean',
            'published_at' => 'datetime', 'superseded_at' => 'datetime',
            'submitted_for_review_at' => 'datetime',
            'approved_at' => 'datetime',
            'price' => 'decimal:2',
            'discount_price' => 'decimal:2',
            'duration_hours' => 'decimal:1',
            'avg_rating' => 'decimal:2',
            'total_enrollments' => 'integer',
            'version' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function modules()
    {
        return $this->hasMany(Module::class)->orderBy('sort_order');
    }

    public function lessons()
    {
        return $this->hasManyThrough(Lesson::class, Module::class);
    }

    public function resources()
    {
        return $this->hasMany(Resource::class)->orderBy('sort_order');
    }

    public function enrollments()
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function enrolledUsers()
    {
        return $this->belongsToMany(User::class, 'course_enrollments')
            ->withPivot('progress_pct', 'status', 'enrolled_at', 'completed_at')
            ->withTimestamps();
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getIsFreeAttribute(): bool
    {
        return (float) $this->price === 0.0;
    }

    public function getEffectivePriceAttribute(): float
    {
        return $this->discount_price && $this->discount_price < $this->price
            ? (float) $this->discount_price
            : (float) $this->price;
    }

    public function getModuleCountAttribute(): int
    {
        return $this->modules()->count();
    }

    public function getLessonCountAttribute(): int
    {
        return $this->lessons()->count();
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopePublished($query)
    {
        return $query->where('courses.is_published', true);
    }

    public function scopeFree($query)
    {
        return $query->where('price', 0);
    }

    public function scopeByLevel($query, string $level)
    {
        return $query->where('level', $level);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
                ->orWhere('subtitle', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%");
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isEnrolledBy(User $user): bool
    {
        return $this->enrollments()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();
    }

    public function enrollmentFor(User $user): ?CourseEnrollment
    {
        return $this->enrollments()
            ->where('user_id', $user->id)
            ->first();
    }

    protected static function booted(): void
    {
        static::creating(function (Course $course) {
            if (empty($course->uuid)) {
                $course->uuid = (string) Str::uuid();
            }
            if (empty($course->slug)) {
                $course->slug = static::uniqueSlug((string) $course->title);
            }
        });
    }

    /** A slug no course (including soft-deleted ones, which keep the unique index) already uses. */
    public static function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'course';
        $slug = $base;
        for ($suffix = 2; static::withTrashed()->where('slug', $slug)->exists(); $suffix++) {
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }
}
