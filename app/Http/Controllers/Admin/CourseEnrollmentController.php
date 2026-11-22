<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CourseEnrollmentController extends Controller
{
    public function index(Request $request, Course $course)
    {
        $this->authorizeAdmin($request);
        $filters = $request->validate([
            'search' => 'nullable|string|max:100',
            'status' => ['nullable', Rule::in(['active', 'completed', 'expired', 'suspended'])],
        ]);
        $counts = $course->enrollments()->selectRaw('status, COUNT(*) AS aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $enrollments = $course->enrollments()
            ->with('user:id,first_name,last_name,email')
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->whereHas('user', fn ($users) => $this->searchUsers($users, $search)))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest('enrolled_at')->orderByDesc('id')->paginate(15)->withQueryString();

        return Inertia::render('Admin/Courses/Enrollments', [
            'course' => $course->only(['id', 'title', 'is_published', 'superseded_at', 'price', 'currency']),
            'enrollments' => $enrollments,
            'counts' => $counts,
            'filters' => $filters,
        ]);
    }

    public function search(Request $request, Course $course)
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate(['search' => 'required|string|min:2|max:100']);
        $users = User::role('learner')->where('is_active', true)
            ->with(['organization:id,name', 'enrollments' => fn ($q) => $q->where('course_id', $course->id)->select(['id', 'user_id', 'status'])]);
        $this->searchUsers($users, $validated['search']);

        return response()->json(['learners' => $users->orderBy('first_name')->orderBy('last_name')->orderBy('id')->limit(20)
            ->get(['id', 'first_name', 'last_name', 'email', 'organization_id'])
            ->map(fn ($user) => [
                'id' => $user->id, 'name' => $user->full_name, 'email' => $user->email,
                'organization' => $user->organization?->name,
                'enrollment_status' => $user->enrollments->first()?->status,
            ])])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, Course $course)
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'deadline' => 'nullable|date_format:Y-m-d|after_or_equal:today',
        ]);
        if ($course->superseded_at) {
            throw ValidationException::withMessages(['user_id' => 'This course has been replaced. Enroll the learner in the current course version.']);
        }
        $learner = User::role('learner')->where('is_active', true)->find($validated['user_id']);
        if (! $learner) {
            throw ValidationException::withMessages(['user_id' => 'Choose an active learner account.']);
        }

        $enrollment = DB::transaction(function () use ($course, $learner, $validated) {
            // The unique user/course key also makes concurrent requests idempotent.
            // Existing progress, completion and suspended/expired access remain intact.
            $enrollment = $course->enrollments()->firstOrCreate(['user_id' => $learner->id], [
                'status' => 'active', 'enrolled_at' => now(), 'enrolled_by' => 'admin',
                'deadline' => $validated['deadline'] ?? null, 'progress_pct' => 0,
            ]);
            if ($enrollment->wasRecentlyCreated) {
                $course->increment('total_enrollments');
            }

            return $enrollment;
        });

        if (! $enrollment->wasRecentlyCreated) {
            return to_route('admin.courses.learners.index', $course)->with('info', $learner->full_name.' is already enrolled ('.$enrollment->status.'). Existing progress and access settings were kept.');
        }

        return to_route('admin.courses.learners.index', $course)->with('success', $learner->full_name.' has been enrolled in '.$course->title.'.'.($course->is_published ? '' : ' They can start learning when the course is published.'));
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->hasRole('platform_admin'), 403);
    }

    private function searchUsers($query, string $search): void
    {
        foreach (array_slice(preg_split('/\s+/', trim($search), -1, PREG_SPLIT_NO_EMPTY), 0, 6) as $term) {
            $query->where(fn ($q) => $q->where('first_name', 'like', '%'.$term.'%')->orWhere('last_name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%'));
        }
    }
}
