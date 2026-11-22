<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseRating;
use App\Models\LessonProgress;
use App\Models\MediaAsset;
use App\Models\Resource;
use App\Models\User;
use App\Services\CourseCheckoutService;
use App\Services\CoursePrerequisiteService;
use App\Services\LearningAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CourseController extends Controller
{
    /**
     * Course catalog with filters, search, and sorting.
     */
    public function index(Request $request)
    {
        $courses = Course::published()->whereNull('superseded_at')
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
                    'short' => $q->whereNotNull('duration_hours')->where('duration_hours', '<=', 2),
                    'medium' => $q->whereNotNull('duration_hours')->whereBetween('duration_hours', [2.1, 6]),
                    'long' => $q->whereNotNull('duration_hours')->where('duration_hours', '>', 6),
                    default => $q,
                };
            })
            ->when($request->sort, function ($q, $sort) {
                return match ($sort) {
                    'newest' => $q->latest('published_at'),
                    'popular' => $q->orderByDesc('total_enrollments'),
                    'rating' => $q->orderByDesc('avg_rating'),
                    'price_low' => $q->orderBy('price'),
                    'price_high' => $q->orderByDesc('price'),
                    default => $q->latest('published_at'),
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
            'courses' => $courses,
            'categories' => $categories,
            'totalCourses' => Course::published()->whereNull('superseded_at')->count(),
            'filters' => $request->only([
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
            'resources' => fn ($q) => $q->published()->orderBy('sort_order')->select(['id', 'course_id', 'title', 'type', 'description', 'file_type', 'file_size_bytes']),
        ]);
        $course->loadCount(['modules' => fn ($q) => $q->published(), 'lessons' => fn ($q) => $q->published()->whereHas('module', fn ($m) => $m->published()), 'enrollments']);

        $isEnrolled = false;
        $hasEnrollment = false;
        $enrollmentStatus = null;
        $enrollment = null;
        $lessonProgress = [];
        $prerequisiteCourses = app(CoursePrerequisiteService::class)->requirements($course, Auth::user());
        $prerequisitesMet = ! collect($prerequisiteCourses)->contains('completed', false);

        if (Auth::check()) {
            $enrollment = $course->enrollmentFor(Auth::user());
            $hasEnrollment = (bool) $enrollment;
            $enrollmentStatus = $enrollment?->status;
            $isEnrolled = $enrollment?->allowsAccess() ?? false;

            if ($enrollment && $enrollment->allowsAccess()) {
                $lessonProgress = LessonProgress::where('user_id', Auth::id())
                    ->whereIn('lesson_id', $course->modules->flatMap(fn ($module) => $module->lessons->pluck('id')))
                    ->get()
                    ->keyBy('lesson_id')
                    ->map(fn ($p) => [
                        'status' => $p->status,
                        'progress_pct' => $p->progress_pct,
                        'completed' => $p->status === 'completed',
                    ]);
            }
        }

        $sequenceLocks = $isEnrolled
            ? app(LearningAccessService::class)->sequentialLocks($course, Auth::user(), $course->modules->flatMap(fn ($module) => $module->lessons->map(fn ($lesson) => ['id' => $lesson->id, 'title' => $lesson->title]))->values()->all())
            : [];

        foreach ($course->modules as $module) {
            $module->lessons->transform(function ($lesson) use ($enrollment, $isEnrolled, $prerequisitesMet, $sequenceLocks) {
                $isUnlocked = $lesson->isUnlockedFor($enrollment);
                $canAccess = ($isEnrolled || $lesson->is_free_preview) && $isUnlocked && $prerequisitesMet && ! isset($sequenceLocks[$lesson->id]);
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
                    (! $prerequisitesMet ? 'Complete course prerequisites' : $blockedLabel)
                        ?? ($isUnlocked && isset($sequenceLocks[$lesson->id]) ? 'Complete “'.$sequenceLocks[$lesson->id].'” first' : null)
                        ?? ($isEnrolled || $lesson->is_free_preview
                        ? $lesson->availabilityLabel($enrollment)
                        : 'Enroll to unlock')
                );

                return $lesson;
            });
        }

        $ratings = $this->buildRatingsPayload($course);

        return Inertia::render('Courses/Show', [
            'checkoutOffer' => app(CourseCheckoutService::class)->offer($course),
            'course' => $course,
            'modules' => $course->modules,
            'resources' => $course->resources,
            'isEnrolled' => $isEnrolled,
            'hasEnrollment' => $hasEnrollment,
            'enrollmentStatus' => $enrollmentStatus,
            'enrollment' => $enrollment,
            'lessonProgress' => $lessonProgress,
            'ratings' => $ratings,
            'prerequisiteCourses' => $prerequisiteCourses,
        ]);
    }

    /**
     * Download a course resource (enrollment required).
     */
    public function downloadResource(Course $course, Resource $resource)
    {
        abort_unless($resource->course_id === $course->id, 404);

        app(LearningAccessService::class)->assertResource($resource, Auth::user());
        $url = $resource->file_url ?? '';
        $id = basename(parse_url($url, PHP_URL_PATH) ?: '');
        $asset = MediaAsset::where('kind', 'document')->find($id);
        abort_unless($asset && $url === $asset->url(), 409, 'This resource needs a protected-file migration. Please contact support.');
        abort_if(config('media.require_scan') && ! $asset->scanned_at, 423, 'This file is awaiting its security scan.');
        abort_unless(Storage::disk('local')->exists($asset->path), 404, 'Resource file is unavailable.');
        $resource->increment('download_count');

        return redirect($asset->url().'?download=1')->header('Cache-Control', 'private, no-store');
    }

    /**
     * Enroll the current user in a course.
     */
    public function enroll(Course $course)
    {
        abort_unless($course->is_published && ! $course->superseded_at, 404);
        $user = Auth::user();
        app(LearningAccessService::class)->assertActiveAccount($user);
        app(CoursePrerequisiteService::class)->assertSatisfied($course, $user);

        $enrollment = $course->enrollmentFor($user);
        if ($enrollment?->allowsAccess()) {
            return back()->with('info', 'You are already enrolled in this course.');
        }
        if ($enrollment && in_array($enrollment->status, ['suspended', 'expired'], true)) {
            return back()->withErrors(['checkout' => 'Ask the learning team to review your paused or expired enrollment before purchasing again.']);
        }
        $offer = app(CourseCheckoutService::class)->offer($course);
        if (! $offer['purchasable']) {
            return back()->withErrors(['checkout' => 'Checkout for this course is currently unavailable. Contact the learning team.']);
        }

        // For free courses, enroll directly
        if ((float) $offer['price'] === 0.0) {
            DB::transaction(function () use ($user, $course) {
                User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                $current = $course->enrollments()->firstOrCreate(['user_id' => $user->id], ['enrolled_at' => now(), 'enrolled_by' => 'self', 'status' => 'active']);
                if (in_array($current->status, ['active', 'completed'], true) && ! $current->allowsAccess()) {
                    $current->update(['source_order_id' => null, 'organization_course_license_id' => null]);
                }
            }, 3);

            return back()->with('success', 'Successfully enrolled! Start learning now.');
        }

        $product = app(CourseCheckoutService::class)->product($course);

        return redirect('/storefront/'.$product->slug);
    }

    private function buildRatingsPayload(Course $course): array
    {
        $distribution = CourseRating::distributionForCourse($course->id);

        return [
            'average' => CourseRating::averageForCourse($course->id) ?? (float) $course->avg_rating,
            'distribution' => $distribution,
            'total' => array_sum($distribution),
            'reviews' => CourseRating::where('course_id', $course->id)
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
