<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationCourseLicense extends Model
{
    protected $fillable = ['organization_id', 'course_id', 'order_item_id', 'granted_by', 'quantity', 'status', 'expires_at', 'reason'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'expires_at' => 'datetime'];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function assignments()
    {
        return $this->hasMany(OrganizationCourseAssignment::class, 'license_id');
    }

    public function availableForAssignment(): bool
    {
        return $this->status === 'active' && (! $this->expires_at || $this->expires_at->isFuture())
            && $this->organization?->is_active
            && (! $this->order_item_id || $this->orderItem?->order?->status === 'paid');
    }

    public function permits(CourseEnrollment $enrollment): bool
    {
        return $this->course_id === $enrollment->course_id && $this->availableForAssignment()
            && User::whereKey($enrollment->user_id)->where('organization_id', $this->organization_id)->where('is_active', true)->exists()
            && $this->assignments()->where('user_id', $enrollment->user_id)->where('enrollment_id', $enrollment->id)->whereNull('released_at')->exists();
    }
}
