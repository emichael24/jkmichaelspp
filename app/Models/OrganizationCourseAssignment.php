<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationCourseAssignment extends Model
{
    protected $fillable = ['license_id', 'user_id', 'enrollment_id', 'assigned_by', 'released_at'];

    protected function casts(): array
    {
        return ['released_at' => 'datetime'];
    }

    public function license()
    {
        return $this->belongsTo(OrganizationCourseLicense::class, 'license_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function enrollment()
    {
        return $this->belongsTo(CourseEnrollment::class, 'enrollment_id');
    }
}
