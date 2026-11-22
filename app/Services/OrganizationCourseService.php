<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CourseEnrollment;
use App\Models\Department;
use App\Models\Organization;
use App\Models\OrganizationCourseAssignment;
use App\Models\OrganizationCourseLicense;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OrganizationCourseService
{
    public function organization(User $actor): Organization
    {
        app(LearningAccessService::class)->assertActiveAccount($actor);
        abort_unless($actor->hasRole('org_admin') && $actor->organization?->is_active, 403);

        return $actor->organization;
    }

    public function assign(User $actor, OrganizationCourseLicense $license, array $userIds, ?int $departmentId, ?string $deadline): array
    {
        $org = $this->organization($actor);
        abort_unless($license->organization_id === $org->id, 404);

        return DB::transaction(function () use ($actor, $license, $userIds, $departmentId, $deadline, $org) {
            Organization::whereKey($org->id)->lockForUpdate()->firstOrFail();
            $license = OrganizationCourseLicense::whereKey($license->id)->lockForUpdate()->firstOrFail();
            if (! $license->availableForAssignment() || ! $license->course?->is_published || $license->course->superseded_at) {
                throw ValidationException::withMessages(['license' => 'This license or course is unavailable. Ask your platform administrator to review its status.']);
            }
            if ($departmentId) {
                $department = Department::where('organization_id', $org->id)->findOrFail($departmentId);
                $userIds = $department->users()->where('users.organization_id', $org->id)->where('users.is_active', true)
                    ->whereHas('roles', fn ($q) => $q->where('name', 'learner'))->pluck('users.id')->all();
            }
            $userIds = array_values(array_unique($userIds));
            if (! $userIds || count($userIds) > 1000) {
                throw ValidationException::withMessages(['members' => 'Select between 1 and 1,000 active learners. For larger departments, assign members in batches.']);
            }
            $users = User::where('organization_id', $org->id)->where('is_active', true)->whereIn('id', $userIds)
                ->whereHas('roles', fn ($q) => $q->where('name', 'learner'))->orderBy('id')->lockForUpdate()->get();
            if ($users->count() !== count($userIds)) {
                throw ValidationException::withMessages(['members' => 'Every selected person must be an active learner in your organization.']);
            }
            $created = 0;
            $skipped = 0;
            $used = $license->assignments()->whereNull('released_at')->count();
            foreach ($users as $user) {
                $enrollment = CourseEnrollment::where('user_id', $user->id)->where('course_id', $license->course_id)->lockForUpdate()->first();
                if ($enrollment && in_array($enrollment->status, ['suspended', 'expired'], true)) {
                    throw ValidationException::withMessages(['members' => $user->full_name.' has a suspended or expired enrollment. Ask the platform administrator to review it first. No assignments were changed.']);
                }
                if ($enrollment?->allowsAccess()) {
                    $skipped++;

                    continue;
                }
                try {
                    app(CoursePrerequisiteService::class)->assertSatisfied($license->course, $user);
                } catch (HttpException $e) {
                    if ($e->getStatusCode() !== 403) {
                        throw $e;
                    }
                    throw ValidationException::withMessages(['members' => $user->full_name.' must complete the course prerequisites first. No assignments were changed.']);
                }
                $assignment = $license->assignments()->where('user_id', $user->id)->first();
                $occupies = $assignment && ! $assignment->released_at;
                if (! $occupies && $used >= $license->quantity) {
                    throw ValidationException::withMessages(['members' => 'There are not enough course places for this selection. Buy or allocate more places, or release an unused assignment. No assignments were changed.']);
                }
                if (! $enrollment) {
                    $enrollment = CourseEnrollment::create(['user_id' => $user->id, 'course_id' => $license->course_id,
                        'enrolled_by' => 'admin', 'enrolled_at' => now(), 'status' => 'active', 'progress_pct' => 0,
                        'deadline' => $deadline, 'organization_course_license_id' => $license->id]);
                    $license->course()->increment('total_enrollments');
                } else {
                    // Preserve earned completion and progress when restoring an unavailable grant.
                    $enrollment->update(['organization_course_license_id' => $license->id, 'source_order_id' => null, 'deadline' => $deadline]);
                }
                OrganizationCourseAssignment::updateOrCreate(['license_id' => $license->id, 'user_id' => $user->id],
                    ['enrollment_id' => $enrollment->id, 'assigned_by' => $actor->id, 'released_at' => null]);
                if (! $occupies) {
                    $used++;
                }
                $created++;
                AuditLog::log('organization_course_assigned', CourseEnrollment::class, $enrollment->id, null, ['license_id' => $license->id, 'department_id' => $departmentId, 'deadline' => $deadline]);
            }

            return compact('created', 'skipped');
        }, 3);
    }

    public function release(User $actor, OrganizationCourseLicense $license, OrganizationCourseAssignment $assignment): void
    {
        $org = $this->organization($actor);
        abort_unless($license->organization_id === $org->id && $assignment->license_id === $license->id, 404);
        DB::transaction(function () use ($org, $license, $assignment) {
            Organization::whereKey($org->id)->lockForUpdate()->firstOrFail();
            OrganizationCourseLicense::whereKey($license->id)->lockForUpdate()->firstOrFail();
            $assignment = OrganizationCourseAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            if (! $assignment->released_at) {
                $assignment->update(['released_at' => now()]);
                AuditLog::log('organization_course_released', CourseEnrollment::class, $assignment->enrollment_id, null, ['license_id' => $license->id]);
            }
        }, 3);
    }
}
