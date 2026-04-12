<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\QuizAttempt;
use App\Models\DailyActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Admin dashboard with aggregate metrics.
     */
    public function index(): Response
    {
        $now = now();
        $thirtyDaysAgo = $now->copy()->subDays(30);
        $sevenDaysAgo = $now->copy()->subDays(7);

        // User metrics
        $totalUsers = User::where('is_active', true)->count();
        $newUsersThisMonth = User::where('created_at', '>=', $thirtyDaysAgo)->count();
        $dau = DailyActivityLog::where('date', $now->toDateString())->distinct('user_id')->count('user_id');
        $wau = DailyActivityLog::where('date', '>=', $sevenDaysAgo->toDateString())->distinct('user_id')->count('user_id');

        // Course metrics
        $totalCourses = Course::where('is_published', true)->count();
        $totalEnrollments = CourseEnrollment::count();
        $completedEnrollments = CourseEnrollment::where('status', 'completed')->count();
        $completionRate = $totalEnrollments > 0 ? round(($completedEnrollments / $totalEnrollments) * 100, 1) : 0;

        // Revenue metrics
        $totalRevenue = Payment::where('status', 'successful')->sum('amount');
        $monthlyRevenue = Payment::where('status', 'successful')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->sum('amount');

        // Top courses
        $topCourses = Course::withCount('enrollments')
            ->orderByDesc('enrollments_count')
            ->limit(5)
            ->get(['id', 'title']);

        // At-risk users (no activity in 7 days but active enrollment)
        $atRiskCount = CourseEnrollment::where('status', 'active')
            ->whereHas('user', function ($q) use ($sevenDaysAgo) {
                $q->where(function ($q2) use ($sevenDaysAgo) {
                    $q2->where('last_login_at', '<', $sevenDaysAgo)
                        ->orWhereNull('last_login_at');
                });
            })
            ->distinct('user_id')
            ->count('user_id');

        // Recent enrollment trend (daily for 30 days)
        $enrollmentTrend = CourseEnrollment::where('created_at', '>=', $thirtyDaysAgo)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date');

        // Recent user registrations
        $recentUsers = User::latest()
            ->limit(8)
            ->get()
            ->map(fn ($u) => [
                'id'       => $u->id,
                'name'     => $u->full_name,
                'email'    => $u->email,
                'role'     => $u->getRoleNames()->first() ?? 'learner',
                'date'     => $u->created_at->format('M d, Y'),
                'verified' => $u->email_verified_at !== null,
            ]);

        return Inertia::render('Admin/Dashboard', [
            'metrics' => [
                'totalUsers' => $totalUsers,
                'newUsersThisMonth' => $newUsersThisMonth,
                'dau' => $dau,
                'wau' => $wau,
                'totalCourses' => $totalCourses,
                'totalEnrollments' => $totalEnrollments,
                'completionRate' => $completionRate,
                'totalRevenue' => $totalRevenue,
                'monthlyRevenue' => $monthlyRevenue,
                'atRiskCount' => $atRiskCount,
            ],
            'topCourses' => $topCourses,
            'enrollmentTrend' => $enrollmentTrend,
            'recentUsers' => $recentUsers,
        ]);
    }
}
