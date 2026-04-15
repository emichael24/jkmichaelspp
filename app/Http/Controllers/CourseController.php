<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseRating;
use App\Models\Resource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
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
            ->when($request->price, function ($q, $price) {
                return match ($price) {
                    'free' => $q->whereRaw('COALESCE(discount_price, price) = 0'),
                    'paid' => $q->whereRaw('COALESCE(discount_price, price) > 0'),
                    default => $q,
                };
            })
            ->when($request->price_min, fn ($q, $min) => $q->whereRaw('COALESCE(discount_price, price) >= ?', [$min]))
            ->when($request->price_max, fn ($q, $max) => $q->whereRaw('COALESCE(discount_price, price) <= ?', [$max]))
            ->when($request->rating, fn ($q, $rating) => $q->where('avg_rating', '>=', $rating))
            ->when($request->duration, function ($q, $duration) {
                return match ($duration) {
                    'short'  => $q->whereNotNull('duration_hours')->where('duration_hours', '<=', 2),
                    'medium' => $q->whereNotNull('duration_hours')->whereBetween('duration_hours', [2.1, 6]),
                    'long'   => $q->whereNotNull('duration_hours')->where('duration_hours', '>', 6),
                    default  => $q,
                };
            })
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
            'filters'      => $request->only([
                'search', 'category', 'level', 'type', 'sort', 'price', 'price_min', 'price_max', 'rating', 'duration',
            ]),
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
                'duration_minutes', 'is_free_preview', 'unlock_type',
                'unlock_at', 'unlock_days_after_enrollment',
            ]),
            'resources' => fn ($q) => $q->published()->orderBy('sort_order'),
        ]);
        $course->loadCount(['modules', 'lessons', 'enrollments']);

        $isEnrolled = false;
        $hasEnrollment = false;
        $enrollmentStatus = null;
        $enrollment = null;
        $lessonProgress = [];

        if (Auth::check()) {
            $enrollment = $course->enrollmentFor(Auth::user());
            $hasEnrollment = (bool) $enrollment;
            $enrollmentStatus = $enrollment?->status;
            $isEnrolled = $enrollment?->allowsAccess() ?? false;

            if ($enrollment && $enrollment->allowsAccess()) {
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

        foreach ($course->modules as $module) {
            $module->lessons->transform(function ($lesson) use ($enrollment, $isEnrolled) {
                $isUnlocked = $lesson->isUnlockedFor($enrollment);
                $canAccess = ($isEnrolled || $lesson->is_free_preview) && $isUnlocked;
                $blockedLabel = null;

                if ($enrollment && ! $enrollment->allowsAccess() && ! $lesson->is_free_preview) {
                    $blockedLabel = $enrollment->status === 'suspended'
                        ? 'Enrollment paused'
                        : 'Enrollment expired';
                }

                $lesson->setAttribute('can_access', $canAccess);
                $lesson->setAttribute('is_locked', ! $canAccess);
                $lesson->setAttribute(
                    'availability_label',
                    $blockedLabel ?? ($isEnrolled || $lesson->is_free_preview
                        ? $lesson->availabilityLabel($enrollment)
                        : 'Enroll to unlock')
                );

                return $lesson;
            });
        }

        $ratings = $this->buildRatingsPayload($course);

        return Inertia::render('Courses/Show', [
            'course'         => $course,
            'modules'        => $course->modules,
            'resources'      => $course->resources,
            'isEnrolled'     => $isEnrolled,
            'hasEnrollment'  => $hasEnrollment,
            'enrollmentStatus' => $enrollmentStatus,
            'enrollment'     => $enrollment,
            'lessonProgress' => $lessonProgress,
            'ratings'        => $ratings,
        ]);
    }

    /**
     * Download a course resource (enrollment required).
     */
    public function downloadResource(Course $course, Resource $resource)
    {
        abort_unless($resource->course_id === $course->id, 404);

        $user = Auth::user();
        $enrollment = $course->enrollmentFor($user);

        if (! $enrollment || ! $enrollment->allowsAccess()) {
            return back()->with('error', 'Please enroll in this course to access resources.');
        }

        $resource->increment('download_count');

        $url = $resource->file_url;
        if (! $url) {
            return back()->with('error', 'Resource file not available.');
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return Inertia::location($url);
        }

        if (str_starts_with($url, 's3://') || str_starts_with($url, 'uploads/') || ! str_contains($url, '://')) {
            $signedUrl = Storage::temporaryUrl($url, now()->addMinutes(30));

            return Inertia::location($signedUrl);
        }

        return Inertia::location($url);
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

    private function buildRatingsPayload(Course $course): array
    {
        $distribution = CourseRating::distributionForCourse($course->id);

        return [
            'average'    => CourseRating::averageForCourse($course->id) ?? (float) $course->avg_rating,
            'distribution' => $distribution,
            'total'      => array_sum($distribution),
            'reviews'    => CourseRating::where('course_id', $course->id)
                ->whereNotNull('review')
                ->with('user:id,first_name,last_name,avatar')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(),
            'userRating' => Auth::check()
                ? CourseRating::where('course_id', $course->id)
                    ->where('user_id', Auth::id())
                    ->first(['id', 'rating', 'review'])
                : null,
        ];
    }
}
