<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Department;
use App\Models\OrganizationCourseAssignment;
use App\Models\OrganizationCourseLicense;
use App\Models\User;
use App\Services\CourseCheckoutService;
use App\Services\OrganizationCourseService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CourseLicenseController extends Controller
{
    public function index(Request $request, OrganizationCourseService $service)
    {
        $org = $service->organization($request->user());
        $filters = $request->validate(['search' => 'nullable|string|max:100']);
        $licenses = OrganizationCourseLicense::where('organization_id', $org->id)->with(['course:id,title,slug,is_published', 'orderItem.order'])
            ->withCount(['assignments as used' => fn ($q) => $q->whereNull('released_at')])
            ->when($filters['search'] ?? null, fn ($q, $term) => $q->whereHas('course', fn ($c) => $c->where('title', 'like', '%'.$term.'%')))
            ->latest('id')->paginate(12)->withQueryString()->through(fn ($license) => [
                'id' => $license->id, 'course' => $license->course?->only('id', 'title', 'slug', 'is_published'), 'quantity' => $license->quantity,
                'used' => $license->used, 'available' => max(0, $license->quantity - $license->used),
                'active' => $license->availableForAssignment(), 'status' => $license->status,
                'expires_at' => $license->expires_at?->toDateString(), 'source' => $license->order_item_id ? 'Purchased' : 'Allocated',
            ]);

        return Inertia::render('Organization/Courses/Index', ['organization' => $org->only('id', 'name'), 'licenses' => $licenses, 'filters' => $filters]);
    }

    public function catalog(Request $request, OrganizationCourseService $service, CourseCheckoutService $checkout)
    {
        $org = $service->organization($request->user());
        $filters = $request->validate(['search' => 'nullable|string|max:100']);
        $courses = Course::published()->whereNull('superseded_at')
            ->when($filters['search'] ?? null, fn ($q, $term) => $q->where('title', 'like', '%'.$term.'%'))
            ->orderBy('title')->paginate(12)->withQueryString()->through(fn ($course) => [
                ...$course->only('id', 'title', 'subtitle', 'level', 'duration_hours'), ...$checkout->offer($course),
            ]);

        return Inertia::render('Organization/Courses/Catalog', ['organization' => $org->only('id', 'name', 'contact_email', 'address', 'country'),
            'courses' => $courses, 'filters' => $filters, 'gateways' => app(PaymentService::class)->availableGateways()]);
    }

    public function purchase(Request $request, Course $course, OrganizationCourseService $service, CourseCheckoutService $checkout, PaymentService $payments)
    {
        $service->organization($request->user());
        $data = $request->validate(['quantity' => 'required|integer|min:1|max:10000', 'payment_gateway' => 'required|in:paystack,flutterwave,stripe', 'discount_code' => 'nullable|string|max:100']);
        $product = $checkout->product($course);
        $result = $payments->createOrder($request->user(), [['product_id' => $product->id, 'quantity' => $data['quantity']]], $data['discount_code'] ?? null, $data['payment_gateway'], true);
        if ($result['checkout']['authorization_url'] ?? null) {
            return Inertia::location($result['checkout']['authorization_url']);
        }

        return redirect('/organization/billing/'.$result['order']->uuid)->with(($result['checkout']['completed'] ?? false) ? 'success' : 'warning',
            ($result['checkout']['completed'] ?? false) ? 'Course places are ready to assign.' : ($result['checkout']['error'] ?? 'Your order is saved. Resume checkout or check payment status.'));
    }

    public function show(Request $request, OrganizationCourseLicense $license, OrganizationCourseService $service)
    {
        $org = $service->organization($request->user());
        abort_unless($license->organization_id === $org->id, 404);
        $used = $license->assignments()->whereNull('released_at')->count();
        $assignments = $license->assignments()->with(['user:id,organization_id,first_name,last_name,email,is_active', 'enrollment:id,progress_pct,status,deadline,organization_course_license_id'])
            ->whereNull('released_at')->latest('id')->paginate(20)->through(fn ($a) => [
                'id' => $a->id, 'member' => $a->user?->organization_id === $org->id ? $a->user->only('id', 'first_name', 'last_name', 'email', 'is_active') : null,
                'progress_pct' => $a->enrollment?->progress_pct, 'status' => $a->enrollment?->status,
                'deadline' => $a->enrollment?->deadline?->toDateString(),
            ]);

        return Inertia::render('Organization/Courses/Show', ['organization' => $org->only('id', 'name'),
            'license' => [...$license->only('id', 'quantity', 'status'), 'expires_at' => $license->expires_at?->toDateString(),
                'course' => $license->course?->only('id', 'title', 'slug', 'is_published'), 'active' => $license->availableForAssignment(), 'used' => $used, 'available' => max(0, $license->quantity - $used)],
            'assignments' => $assignments, 'departments' => Department::where('organization_id', $org->id)->orderBy('name')->get(['id', 'name'])]);
    }

    public function members(Request $request, OrganizationCourseLicense $license, OrganizationCourseService $service)
    {
        $org = $service->organization($request->user());
        abort_unless($license->organization_id === $org->id, 404);
        $data = $request->validate(['search' => 'required|string|min:2|max:100']);

        return response()->json(['members' => User::where('organization_id', $org->id)->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('name', 'learner'))
            ->where(fn ($q) => $q->where('first_name', 'like', '%'.$data['search'].'%')->orWhere('last_name', 'like', '%'.$data['search'].'%')->orWhere('email', 'like', '%'.$data['search'].'%'))
            ->orderBy('first_name')->limit(30)->get(['id', 'first_name', 'last_name', 'email'])])->header('Cache-Control', 'private, no-store');
    }

    public function assign(Request $request, OrganizationCourseLicense $license, OrganizationCourseService $service)
    {
        $data = $request->validate(['user_ids' => 'nullable|array|max:1000', 'user_ids.*' => 'integer|distinct',
            'department_id' => 'nullable|integer', 'deadline' => 'nullable|date_format:Y-m-d|after_or_equal:today']);
        $result = $service->assign($request->user(), $license, $data['user_ids'] ?? [], $data['department_id'] ?? null, $data['deadline'] ?? null);

        return back()->with('success', $result['created'].' learners assigned. '.$result['skipped'].' already had access and were kept unchanged.');
    }

    public function release(Request $request, OrganizationCourseLicense $license, OrganizationCourseAssignment $assignment, OrganizationCourseService $service)
    {
        $service->release($request->user(), $license, $assignment);

        return back()->with('success', 'Place released. Learning progress and earned certificates are preserved.');
    }
}
