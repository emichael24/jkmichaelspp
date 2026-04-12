<?php

namespace App\Http\Controllers;

use App\Models\CareerPathway;
use App\Models\CourseEnrollment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PathwayController extends Controller
{
    /**
     * List all career pathways.
     */
    public function index(): Response
    {
        $pathways = CareerPathway::withCount('courses')->get();
        $userId = auth()->id();

        $pathways = $pathways->map(function ($pathway) use ($userId) {
            $progress = $pathway->getProgressForUser($userId);
            $pathway->user_progress = $progress;
            return $pathway;
        });

        return Inertia::render('Pathways/Index', [
            'pathways' => $pathways,
        ]);
    }

    /**
     * Show a pathway with courses and progress.
     */
    public function show(string $slug): Response
    {
        $pathway = CareerPathway::where('slug', $slug)
            ->with(['courses' => function ($q) {
                $q->with('category:id,name');
            }])
            ->firstOrFail();

        $userId = auth()->id();
        $progress = $pathway->getProgressForUser($userId);
        $nextCourse = $pathway->getNextCourseForUser($userId);

        // Get enrollment status for each course
        $courseIds = $pathway->courses->pluck('id');
        $enrollments = CourseEnrollment::where('user_id', $userId)
            ->whereIn('course_id', $courseIds)
            ->get()
            ->keyBy('course_id');

        $courses = $pathway->courses->map(function ($course) use ($enrollments) {
            $enrollment = $enrollments->get($course->id);
            $course->enrollment_status = $enrollment?->status;
            $course->enrollment_progress = $enrollment?->progress_pct ?? 0;
            return $course;
        });

        return Inertia::render('Pathways/Show', [
            'pathway' => $pathway,
            'courses' => $courses,
            'progress' => $progress,
            'nextCourse' => $nextCourse,
        ]);
    }
}
