<?php

namespace App\Models;

use App\Models\Traits\RecordsDomainAudit;
use Illuminate\Database\Eloquent\Model;

class CourseEnrollment extends Model
{
    use RecordsDomainAudit;

    protected $fillable = [
        'user_id',
        'course_id',
        'enrolled_at',
        'enrolled_by',
        'deadline',
        'progress_pct',
        'status',
        'completed_at',
        'source_order_id',
        'organization_course_license_id',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
            'completed_at' => 'datetime',
            'deadline' => 'date',
            'progress_pct' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function allowsAccess(): bool
    {
        return in_array($this->status, ['active', 'completed'], true)
            && (! $this->organization_course_license_id || OrganizationCourseLicense::find($this->organization_course_license_id)?->permits($this))
            && (! $this->source_order_id || Order::whereKey($this->source_order_id)->where('status', 'paid')->exists());
    }
}
