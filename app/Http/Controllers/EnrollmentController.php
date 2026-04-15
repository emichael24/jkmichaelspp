<?php

namespace App\Http\Controllers;

use App\Models\CourseEnrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class EnrollmentController extends Controller
{
    /**
     * My Courses — list enrolled courses with progress.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $status = $request->get('status', 'active');

        $enrollments = CourseEnrollment::where('user_id', $user->id)
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->with([
                'course' => fn ($q) => $q->select([
                    'id', 'title', 'slug', 'subtitle', 'thumbnail',
                    'level', 'type', 'duration_hours', 'category_id',
                ])
                ->with('category:id,name,slug')
                ->withCount(['modules', 'lessons']),
            ])
            ->latest('enrolled_at')
            ->paginate(12)
            ->withQueryString();

        // Get counts per status
        $statusCounts = [
            'all'       => CourseEnrollment::where('user_id', $user->id)->count(),
            'active'    => CourseEnrollment::where('user_id', $user->id)->where('status', 'active')->count(),
            'completed' => CourseEnrollment::where('user_id', $user->id)->where('status', 'completed')->count(),
            'expired'   => CourseEnrollment::where('user_id', $user->id)->where('status', 'expired')->count(),
            'suspended' => CourseEnrollment::where('user_id', $user->id)->where('status', 'suspended')->count(),
        ];

        return Inertia::render('Courses/MyCourses', [
            'enrollments'  => $enrollments,
            'statusCounts' => $statusCounts,
            'currentStatus' => $status,
        ]);
    }
}
