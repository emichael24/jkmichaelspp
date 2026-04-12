<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use App\Models\CourseEnrollment;
use App\Models\Course;
use App\Models\Bookmark;
use App\Models\UserNote;
use App\Models\LessonProgress;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes, Traits\HasSubscriptions;

    protected $fillable = [
        'uuid',
        'organization_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'avatar',
        'timezone',
        'locale',
        'goal',
        'exam_date',
        'daily_minutes',
        'learning_preference',
        'experience_level',
        'accessibility_prefs',
        'notification_prefs',
        'onboarding_completed',
        'is_active',
        'last_login_at',
        'whatsapp_number',
        'whatsapp_session',
        'whatsapp_paired_at',
        'whatsapp_paired_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'exam_date'         => 'date',
            'last_login_at'     => 'datetime',
            'password'          => 'hashed',
            'accessibility_prefs' => 'array',
            'notification_prefs'  => 'array',
            'onboarding_completed' => 'boolean',
            'is_active'           => 'boolean',
            'daily_minutes'       => 'integer',
        ];
    }

    protected $appends = ['full_name', 'initials'];

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getInitialsAttribute(): string
    {
        return strtoupper(
            mb_substr($this->first_name, 0, 1) . mb_substr($this->last_name, 0, 1)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function pairedBy()
    {
        return $this->belongsTo(User::class, 'whatsapp_paired_by');
    }

    public function departments()
    {
        return $this->belongsToMany(Department::class, 'department_user');
    }

    public function enrollments()
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function enrolledCourses()
    {
        return $this->belongsToMany(Course::class, 'course_enrollments')
            ->withPivot('progress_pct', 'status', 'enrolled_at', 'completed_at')
            ->withTimestamps();
    }

    public function bookmarks()
    {
        return $this->hasMany(Bookmark::class);
    }

    public function notes()
    {
        return $this->hasMany(UserNote::class);
    }

    public function lessonProgress()
    {
        return $this->hasMany(LessonProgress::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForOrganization($query, $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->uuid)) {
                $user->uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }
}
