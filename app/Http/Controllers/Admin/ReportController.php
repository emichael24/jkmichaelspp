<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Department;
use App\Models\InternshipTask;
use App\Models\InternshipTrack;
use App\Models\Payment;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * Reports index page.
     */
    public function index(): Response
    {
        return Inertia::render('Admin/Reports/Index');
    }

    /**
     * Executive summary report.
     */
    public function executive(): Response
    {
        $thirtyDaysAgo = now()->subDays(30);

        $users = [
            'total' => User::where('is_active', true)->count(),
            'new_this_month' => User::where('created_at', '>=', $thirtyDaysAgo)->count(),
            'byRole' => User::selectRaw('COUNT(*) as count')
                ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->selectRaw('roles.name as role')
                ->groupBy('roles.name')
                ->pluck('count', 'role'),
        ];

        $courses = [
            'total' => Course::count(),
            'published' => Course::where('is_published', true)->count(),
            'enrollments' => CourseEnrollment::count(),
            'completions' => CourseEnrollment::where('status', 'completed')->count(),
        ];

        $revenue = [
            'total' => Payment::where('status', 'success')->sum('amount'),
            'this_month' => Payment::where('status', 'success')
                ->where('created_at', '>=', $thirtyDaysAgo)->sum('amount'),
            'byGateway' => Payment::where('status', 'success')
                ->selectRaw('gateway, SUM(amount) as total')
                ->groupBy('gateway')
                ->pluck('total', 'gateway'),
        ];

        $assessments = [
            'quiz_attempts' => QuizAttempt::count(),
            'averageScore' => round(QuizAttempt::where('status', 'completed')->avg('score_pct') ?? 0, 1),
        ];

        return Inertia::render('Admin/Reports/Executive', [
            'users' => $users,
            'courses' => $courses,
            'revenue' => $revenue,
            'assessments' => $assessments,
        ]);
    }

    /**
     * Compliance report (who completed what).
     */
    public function compliance(Request $request): Response
    {
        $query = CourseEnrollment::with([
            'user:id,first_name,last_name,email',
            'course:id,title',
        ]);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($courseId = $request->input('course_id')) {
            $query->where('course_id', $courseId);
        }

        $enrollments = $query->orderByDesc('updated_at')->paginate(25);
        $courses = Course::select('id', 'title')->orderBy('title')->get();
        $auditLogs = AuditLog::where('action', 'bulk_enrollment_update')
            ->where('auditable_type', CourseEnrollment::class)
            ->whereIn('auditable_id', $enrollments->pluck('id'))
            ->with('user:id,first_name,last_name,email')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('auditable_id')
            ->map(fn ($logs) => $logs->first());

        return Inertia::render('Admin/Reports/Compliance', [
            'enrollments' => $enrollments,
            'courses' => $courses,
            'filters' => $request->only('status', 'course_id'),
            'auditLogs' => $auditLogs,
        ]);
    }

    /**
     * Department report.
     */
    public function department(Request $request): Response
    {
        $departments = Department::withCount('users')
            ->get()
            ->map(function ($dept) {
                $userIds = $dept->users()->pluck('id');
                $dept->enrollments_count = CourseEnrollment::whereIn('user_id', $userIds)->count();
                $dept->completions_count = CourseEnrollment::whereIn('user_id', $userIds)->where('status', 'completed')->count();
                $dept->avg_completion = $dept->enrollments_count > 0
                    ? round(($dept->completions_count / $dept->enrollments_count) * 100, 1) : 0;

                return $dept;
            });

        return Inertia::render('Admin/Reports/Department', [
            'departments' => $departments,
        ]);
    }

    /**
     * Internship performance report.
     */
    public function internship(): Response
    {
        $tracks = InternshipTrack::withCount(['enrollments', 'milestones'])
            ->get()
            ->map(function ($track) {
                $track->tasks_count = InternshipTask::whereHas('enrollment', function ($q) use ($track) {
                    $q->where('track_id', $track->id);
                })->count();

                return $track;
            });

        return Inertia::render('Admin/Reports/Internship', [
            'tracks' => $tracks,
        ]);
    }
}
