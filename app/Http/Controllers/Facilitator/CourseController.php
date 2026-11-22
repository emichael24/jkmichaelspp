<?php

namespace App\Http\Controllers\Facilitator;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use App\Notifications\CourseSubmittedForReview;
use App\Services\ContentWorkflowService;
use App\Services\CourseReadinessService;
use App\Services\CurriculumRevisionService;
use App\Services\LessonActivityService;
use App\Services\LessonAuthoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CourseController extends Controller
{
    // ─── List facilitator's own courses ────────────────────────────────────────
    public function index(Request $request)
    {
        $status = $request->validate(['status' => 'nullable|in:all,draft,pending_review,approved,rejected'])['status'] ?? 'all';
        $owned = Course::where('author_id', $request->user()->id);
        $counts = (clone $owned)->selectRaw('approval_status, count(*) as total')->groupBy('approval_status')->pluck('total', 'approval_status');
        $courses = $owned
            ->with('category')
            ->withCount(['modules', 'lessons'])
            ->when($status !== 'all', fn ($query) => $query->where('approval_status', $status))
            ->latest()
            ->paginate(20)->withQueryString();

        return Inertia::render('Facilitator/Courses/Index', [
            'courses' => $courses,
            'counts' => $counts,
            'currentStatus' => $status,
        ]);
    }

    // ─── Create form ────────────────────────────────────────────────────────────
    public function create()
    {
        return Inertia::render('Facilitator/Courses/Create', [
            'categories' => Category::where('is_active', true)->orderBy('name')->get(['id', 'name', 'parent_id']),
        ]);
    }

    // ─── Store new course (draft) ────────────────────────────────────────────────
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'level' => 'required|in:beginner,intermediate,advanced',
            'type' => 'required|in:self_paced,live,certified,bootcamp',
            'price' => 'required|numeric|min:0|max:99999999.99',
            'duration_hours' => 'nullable|numeric|min:0|max:9999.9',
            'currency' => 'nullable|string|size:3',
            'discount_price' => 'nullable|numeric|min:0|lte:price',
            'learning_outcomes' => 'nullable|array',
            'prerequisites' => 'nullable|array',
            'tags' => 'nullable|array',
            'thumbnail' => 'nullable|string|max:500',
        ]);

        $course = Course::create([
            ...$validated,
            'uuid' => (string) Str::uuid(),
            'slug' => $this->uniqueSlug($validated['title']),
            'author_id' => auth()->id(),
            'created_by_role' => 'facilitator',
            'approval_status' => 'draft',
            'is_published' => false,
            'version' => 1,
        ]);

        return redirect()->route('facilitator.courses.edit', ['course' => $course, 'tab' => 'curriculum'])
            ->with('success', 'Course created as draft. Add modules and lessons, then submit for admin approval.');
    }

    // ─── Edit form ───────────────────────────────────────────────────────────────
    public function edit(Course $course)
    {
        $this->authorizeOwnership($course);

        $course->load(['modules' => fn ($q) => $q->orderBy('sort_order'), 'modules.lessons' => fn ($q) => $q->orderBy('sort_order'), 'category']);

        return Inertia::render('Facilitator/Courses/Edit', [
            'course' => $course,
            'readinessIssues' => app(CourseReadinessService::class)->issues($course),
            'categories' => Category::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    // ─── Update course ────────────────────────────────────────────────────────────
    public function update(Request $request, Course $course)
    {
        $this->authorizeOwnership($course);
        $this->ensureEditable($course);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'level' => 'required|in:beginner,intermediate,advanced',
            'type' => 'required|in:self_paced,live,certified,bootcamp',
            'price' => 'required|numeric|min:0|max:99999999.99',
            'duration_hours' => 'nullable|numeric|min:0|max:9999.9',
            'currency' => 'nullable|string|size:3',
            'discount_price' => 'nullable|numeric|min:0|lte:price',
            'learning_outcomes' => 'nullable|array',
            'prerequisites' => 'nullable|array',
            'tags' => 'nullable|array',
            'thumbnail' => 'nullable|string|max:500',
            'preview_video' => 'nullable|string|max:500',
        ]);

        $course->update($validated);

        return back()->with('success', 'Course updated.');
    }

    // ─── Submit course for admin review ──────────────────────────────────────────
    public function submitForReview(Course $course)
    {
        $this->authorizeOwnership($course);

        if (! in_array($course->approval_status, ['draft', 'rejected'])) {
            return back()->with('error', 'This course cannot be submitted for review at this stage.');
        }

        app(CourseReadinessService::class)->assertReady($course);

        app(ContentWorkflowService::class)->submitForReview($course, auth()->user());

        // Notify all platform admins
        User::role('platform_admin')->each(function ($admin) use ($course) {
            $admin->notify(new CourseSubmittedForReview($course));
        });

        return back()->with('success', 'Course submitted for admin review. You will be notified once reviewed.');
    }

    // ─── Delete (only drafts) ─────────────────────────────────────────────────────
    public function destroy(Course $course)
    {
        $this->authorizeOwnership($course);

        if ($course->published_at || $course->is_published || $course->enrollments()->exists() || $course->approval_status !== 'draft') {
            return back()->with('error', 'Only draft courses can be deleted.');
        }

        $course->delete();

        return redirect()->route('facilitator.courses.index')
            ->with('success', 'Course deleted.');
    }

    // ─── Module management ────────────────────────────────────────────────────────
    public function storeModule(Request $request, Course $course)
    {
        $this->authorizeOwnership($course);
        $this->ensureEditable($course);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $module = $course->modules()->create([
            ...$validated,
            'sort_order' => $course->modules()->count(),
            'is_published' => true,
        ]);

        return back()->with(['success' => 'Module added.', 'module' => $module]);
    }

    public function updateModule(Request $request, Course $course, Module $module)
    {
        $this->authorizeOwnership($course);
        $this->ensureEditable($course);
        abort_unless($module->course_id === $course->id, 403);

        $module->update($request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
        ]));

        return back()->with('success', 'Module updated.');
    }

    public function destroyModule(Course $course, Module $module)
    {
        $this->authorizeOwnership($course);
        $this->ensureEditable($course);
        abort_unless($module->course_id === $course->id, 403);

        $module->delete();

        return back()->with('success', 'Module deleted.');
    }

    // ─── Lesson management ────────────────────────────────────────────────────────
    public function createLesson(Request $request, Course $course, Module $module)
    {
        $this->authorizeOwnership($course);
        $this->ensureEditable($course);
        abort_unless($module->course_id === $course->id, 404);

        $course->load(['modules' => fn ($q) => $q->orderBy('sort_order'), 'modules.lessons' => fn ($q) => $q->orderBy('sort_order')]);

        return Inertia::render('Facilitator/Courses/Lessons/Create', [
            'course' => $course,
            'module' => $module,
            'initialType' => in_array($request->query('type'), Lesson::CONTENT_TYPES, true) ? $request->query('type') : 'text',
            'questionBank' => app(LessonActivityService::class)->questionBank($course),
        ]);
    }

    public function editLesson(Course $course, Module $module, Lesson $lesson)
    {
        $this->authorizeOwnership($course);
        $this->ensureEditable($course);
        abort_unless($module->course_id === $course->id && $lesson->module_id === $module->id, 404);

        $course->load(['modules' => fn ($q) => $q->orderBy('sort_order'), 'modules.lessons' => fn ($q) => $q->orderBy('sort_order')]);
        $activities = app(LessonActivityService::class);

        return Inertia::render('Facilitator/Courses/Lessons/Edit', [
            'course' => $course,
            'module' => $module,
            'lesson' => $lesson,
            'activity' => $activities->editorPayload($lesson),
            'questionBank' => $activities->questionBank($course),
        ]);
    }

    public function updateThumbnail(Request $request, Course $course)
    {
        $this->authorizeOwnership($course);
        $this->ensureEditable($course);
        $request->validate(['thumbnail' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048']);
        $course->update(['thumbnail' => $request->file('thumbnail')->store('courses/thumbnails', 'public')]);

        return back()->with('success', 'Thumbnail updated.');
    }

    public function storeLesson(Request $request, Course $course, Module $module)
    {
        $this->authorizeOwnership($course);
        $this->ensureEditable($course);
        abort_unless($module->course_id === $course->id, 403);

        $authoring = app(LessonAuthoringService::class);
        $lesson = $authoring->persist($module, null, $authoring->validate($request), $request->user());

        if ($request->boolean('stay_in_editor')) {
            return redirect()->route('facilitator.courses.lessons.edit', [$course, $module, $lesson])->with('success', 'Lesson saved.');
        }

        return redirect()->route('facilitator.courses.edit', $course)->with('success', 'Lesson added.');
    }

    public function updateLesson(Request $request, Course $course, Module $module, Lesson $lesson)
    {
        $this->authorizeOwnership($course);
        $this->ensureEditable($course);
        abort_unless($module->course_id === $course->id && $lesson->module_id === $module->id, 403);

        $authoring = app(LessonAuthoringService::class);
        $authoring->persist($module, $lesson, $authoring->validate($request, true), $request->user());

        if ($request->boolean('stay_in_editor')) {
            return redirect()->route('facilitator.courses.lessons.edit', [$course, $module, $lesson])->with('success', 'Lesson saved.');
        }

        return redirect()->route('facilitator.courses.edit', $course)->with('success', 'Lesson updated.');
    }

    public function destroyLesson(Course $course, Module $module, Lesson $lesson)
    {
        $this->authorizeOwnership($course);
        $this->ensureEditable($course);
        abort_unless($module->course_id === $course->id && $lesson->module_id === $module->id, 403);

        $lesson->delete();

        return back()->with('success', 'Lesson deleted.');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────────

    protected function authorizeOwnership(Course $course): void
    {
        if ($course->author_id !== auth()->id()) {
            abort(403, 'You do not own this course.');
        }
    }

    protected function ensureEditable(Course $course): void
    {
        app(CurriculumRevisionService::class)->assertEditable($course);
    }

    protected function uniqueSlug(string $title): string
    {
        return Course::uniqueSlug($title);
    }
}
