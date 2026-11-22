<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Organization;
use App\Models\OrganizationCourseLicense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class OrganizationLicenseController extends Controller
{
    public function index(Organization $organization)
    {
        return Inertia::render('Admin/Organizations/Licenses', ['organization' => $organization->only('id', 'name', 'is_active'),
            'licenses' => OrganizationCourseLicense::where('organization_id', $organization->id)->with('course:id,title')
                ->withCount(['assignments as used' => fn ($q) => $q->whereNull('released_at')])->latest('id')->paginate(15)->through(fn ($l) => [
                    ...$l->only('id', 'course_id', 'quantity', 'status', 'reason'), 'course' => $l->course?->only('title'), 'used' => $l->used,
                    'purchased' => (bool) $l->order_item_id, 'expires_at' => $l->expires_at?->toDateString(), 'active' => $l->availableForAssignment(),
                ])]);
    }

    public function courses(Request $request)
    {
        $data = $request->validate(['search' => 'required|string|min:2|max:100']);

        return response()->json(['courses' => Course::published()->whereNull('superseded_at')->where('title', 'like', '%'.$data['search'].'%')
            ->orderBy('title')->limit(20)->get(['id', 'title'])])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, Organization $organization)
    {
        $data = $request->validate(['course_id' => 'required|integer', 'quantity' => 'required|integer|min:1|max:1000000',
            'expires_at' => 'nullable|date_format:Y-m-d|after_or_equal:today', 'reason' => 'required|string|min:5|max:1000']);
        DB::transaction(function () use ($request, $organization, $data) {
            $org = Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            abort_unless($org->is_active, 422, 'Activate the organization before allocating a course.');
            Course::published()->whereNull('superseded_at')->findOrFail($data['course_id']);
            $license = OrganizationCourseLicense::create([...$data, 'expires_at' => empty($data['expires_at']) ? null : $data['expires_at'].' 23:59:59',
                'organization_id' => $org->id, 'granted_by' => $request->user()->id]);
            AuditLog::log('organization_license_allocated', OrganizationCourseLicense::class, $license->id, null, $license->only('organization_id', 'course_id', 'quantity', 'expires_at', 'reason'));
        }, 3);

        return back()->with('success', 'Course places allocated. The organization administrator can now assign learners.');
    }

    public function update(Request $request, Organization $organization, OrganizationCourseLicense $license)
    {
        abort_unless($license->organization_id === $organization->id, 404);
        $data = $request->validate(['quantity' => 'required|integer|min:1|max:1000000', 'status' => 'required|in:active,revoked',
            'expires_at' => 'nullable|date_format:Y-m-d', 'reason' => 'required|string|min:5|max:1000']);
        DB::transaction(function () use ($organization, $license, $data) {
            Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            $license = OrganizationCourseLicense::whereKey($license->id)->lockForUpdate()->firstOrFail();
            if ($license->order_item_id && ((int) $data['quantity'] !== $license->quantity || ($data['expires_at'] ?? null) !== $license->expires_at?->toDateString())) {
                throw ValidationException::withMessages(['quantity' => 'Purchased quantities and terms are retained. Allocate extra places separately.']);
            }
            if ($data['quantity'] < $license->assignments()->whereNull('released_at')->count()) {
                throw ValidationException::withMessages(['quantity' => 'Release assignments before reducing the number of places below current usage.']);
            }
            $before = $license->only('quantity', 'status', 'expires_at', 'reason');
            $license->update([...$data, 'expires_at' => empty($data['expires_at']) ? null : $data['expires_at'].' 23:59:59']);
            AuditLog::log('organization_license_updated', OrganizationCourseLicense::class, $license->id, $before, $license->only('quantity', 'status', 'expires_at', 'reason'));
        }, 3);

        return back()->with('success', 'Course allocation updated.');
    }
}
