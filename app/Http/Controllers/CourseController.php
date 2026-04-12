<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CourseController extends Controller
{
    /**
     * Course catalog with filters, search, and sorting.
     */
    public function index(Request $request)
    {
        $courses = Course::published()
            ->with('category')
            ->withCount(['modules', 'lessons', 'enrollments'])
            ->when($request->search, fn ($q, $s) => $q->search($s))
            ->when($request->category, fn ($q, $c) => $q->where('category_id', $c))
            ->when($request->level, fn ($q, $l) => $q->byLevel($l))
            ->when($request->type, fn ($q, $t) => $q->byType($t))
            ->when($request->sort, function ($q, $sort) {
                return match ($sort) {
                    'newest'     => $q->latest('published_at'),
                    'popular'    => $q->orderByDesc('total_enrollments'),
                    'rating'     => $q->orderByDesc('avg_rating'),
                    'price_low'  => $q->orderBy('price'),
                    'price_high' => $q->orderByDesc('price'),
                    default      => $q->latest('published_at'),
                };
            }, fn ($q) => $q->latest('published_at'))
            ->paginate(12)
            ->withQueryString();

        // Add enrollment progress for current user
        if (Auth::check()) {
            $enrollments = Auth::user()->enrollments()
                ->whereIn('course_id', $courses->pluck('id'))
                ->get()
                ->keyBy('course_id');

            $courses->getCollection()->transform(function ($course) use ($enrollments) {
                $enrollment = $enrollments->get($course->id);
                $course->progress = $enrollment?->progress_pct ?? 0;
                return $course;
            });
        }

        $categories = Category::active()
            ->withCount('courses')
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'icon']);

        return Inertia::render('Courses/Index', [
            'courses'      => $courses,
            'categories'   => $categories,
            'totalCourses' => Course::published()->count(),
            'filters'      => $request->only(['search', 'category', 'level', 'type', 'sort']),
        ]);
    }

    /**
     * Course detail page with modules, lessons, and enrollment status.
     */
    public function show(Course $course)
    {
        abort_unless($course->is_published, 404);

        $course->load([
            'category',
            'author:id,first_name,last_name,avatar',
            'modules' => fn ($q) => $q->published()->orderBy('sort_order'),
            'modules.lessons' => fn ($q) => $q->published()->orderBy('sort_order')->select([
                'id', 'module_id', 'title', 'slug', 'content_type',
                'duration_minutes', 'is_free_preview',
            ]),
            'resources' => fn ($q) => $q->published()->orderBy('sort_order'),
        ]);
        $course->loadCount(['modules', 'lessons', 'enrollments']);

        $isEnrolled = false;
        $enrollment = null;
        $lessonProgress = [];

        if (Auth::check()) {
            $enrollment = $course->enrollmentFor(Auth::user());
            $isEnrolled = (bool) $enrollment;

            if ($isEnrolled) {
                $lessonProgress = Auth::user()->notes()
                    ->pluck('lesson_id')
                    ->toArray();

                // Get progress for each lesson
                $lessonProgress = \App\Models\LessonProgress::where('user_id', Auth::id())
                    ->whereIn('lesson_id', $course->lessons->pluck('id'))
                    ->get()
                    ->keyBy('lesson_id')
                    ->map(fn ($p) => [
                        'status'       => $p->status,
                        'progress_pct' => $p->progress_pct,
                        'completed'    => $p->status === 'completed',
                    ]);
            }
        }

        return Inertia::render('Courses/Show', [
            'course'         => $course,
            'modules'        => $course->modules,
            'resources'      => $course->resources,
            'isEnrolled'     => $isEnrolled,
            'enrollment'     => $enrollment,
            'lessonProgress' => $lessonProgress,
        ]);
    }

    /**
     * Enroll the current user in a course.
     */
    public function enroll(Course $course)
    {
        $user = Auth::user();

        // Check if already enrolled
        if ($course->isEnrolledBy($user)) {
            return back()->with('info', 'You are already enrolled in this course.');
        }

        // For free courses, enroll directly
        if ($course->is_free) {
            $course->enrollments()->create([
                'user_id'     => $user->id,
                'enrolled_at' => now(),
                'enrolled_by' => 'self',
                'status'      => 'active',
            ]);

            return back()->with('success', 'Successfully enrolled! Start learning now.');
        }

        // For paid courses, redirect to payment (placeholder)
        return back()->with('info', 'Paid enrollment coming soon.');
    }
}
