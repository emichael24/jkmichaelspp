<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CourseEnrollment;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    public function updateStatus(Request $request, CourseEnrollment $enrollment)
    {
        $validated = $request->validate([
            'status' => 'required|in:active,completed,expired,suspended',
        ]);

        $enrollment->update(['status' => $validated['status']]);

        return back()->with('success', 'Enrollment status updated.');
    }

    public function autoExpireByDeadline()
    {
        $expiredCount = CourseEnrollment::where('status', 'active')
            ->whereNotNull('deadline')
            ->where('deadline', '<', now()->toDateString())
            ->update(['status' => 'expired']);

        return back()->with('success', "Auto-expired {$expiredCount} enrollments.");
    }

    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'enrollment_ids' => 'required|array|min:1',
            'enrollment_ids.*' => 'integer|exists:course_enrollments,id',
            'status' => 'nullable|in:active,completed,expired,suspended',
            'deadline' => 'nullable|date',
            'clear_deadline' => 'nullable|boolean',
        ]);

        $payload = [];

        if (isset($validated['status'])) {
            $payload['status'] = $validated['status'];
        }

        if (! empty($validated['clear_deadline'])) {
            $payload['deadline'] = null;
        } elseif (! empty($validated['deadline'])) {
            $payload['deadline'] = $validated['deadline'];
        }

        if (empty($payload)) {
            return back()->with('error', 'Select at least one change to apply.');
        }

        $enrollments = CourseEnrollment::whereIn('id', $validated['enrollment_ids'])->get();
        $enrollments->each(function (CourseEnrollment $enrollment) use ($payload) {
            $old = $enrollment->only(['status', 'deadline']);
            $enrollment->update($payload);
            AuditLog::log(
                'bulk_enrollment_update',
                CourseEnrollment::class,
                $enrollment->id,
                $old,
                $enrollment->only(['status', 'deadline'])
            );
        });

        return back()->with('success', 'Enrollments updated.');
    }
}
