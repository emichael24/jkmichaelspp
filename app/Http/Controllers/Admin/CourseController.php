<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Resource;
use App\Services\ContentWorkflowService;
use App\Services\CourseReadinessService;
use App\Services\CurriculumRevisionService;
use App\Services\LessonActivityService;
use App\Services\LessonAuthoringService;
use App\Services\PrivateMediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CourseController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Course CRUD
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $courses = Course::with('category', 'author')
            ->withCount(['modules', 'lessons', 'enrollments'])
            ->when($request->search, fn ($q, $s) => $q->search($s))
            ->when($request->category, fn ($q, $c) => $q->where('category_id', $c))
            ->when($request->level, fn ($q, $l) => $q->byLevel($l))
            ->when($request->type, fn ($q, $t) => $q->byType($t))
            ->when($request->status === 'published', fn ($q) => $q->published())
            ->when($request->status === 'draft', fn ($q) => $q->where('is_published', false))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $categories = Category::active()->orderBy('sort_order')->get(['id', 'name']);

        return Inertia::render('Admin/Courses/Index', [
            'courses' => $courses,
            'categories' => $categories,
            'filters' => $request->only(['search', 'category', 'level', 'type', 'status']),
        ]);
    }

    public function create()
    {
        $categories = Category::active()->orderBy('sort_order')->get(['id', 'name']);

        return Inertia::render('Admin/Courses/Create', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'level' => 'required|in:beginner,intermediate,advanced',
            'type' => 'required|in:self_paced,live,certified,bootcamp',
            'duration_hours' => 'nullable|numeric|min:0|max:9999.9',
            'price' => 'required|numeric|min:0|max:99999999.99',
            'currency' => 'nullable|string|size:3',
            'discount_price' => 'nullable|numeric|min:0|lte:price',
            'learning_outcomes' => 'nullable|array',
            'prerequisites' => 'nullable|array',
            'tags' => 'nullable|array',
            'thumbnail' => 'nullable|image|max:2048',
        ]);

        // Handle thumbnail upload
        if ($request->hasFile('thumbnail')) {
            $validated['thumbnail'] = $request->file('thumbnail')->store('courses/thumbnails', 'public');
        }

        $validated['author_id'] = Auth::id();

        $course = Course::create($validated);

        return redirect()->route('admin.courses.edit', ['course' => $course, 'tab' => 'curriculum'])
            ->with('success', 'Course created successfully. Now add modules and lessons.');
    }

    public function edit(Course $course)
    {
        $course->load([
            'category',
            'modules' => fn ($q) => $q->orderBy('sort_order'),
            'modules.lessons' => fn ($q) => $q->orderBy('sort_order'),
            'resources' => fn ($q) => $q->orderBy('sort_order'),
        ]);
        $course->loadCount(['enrollments', 'lessons']);

        $categories = Category::active()->orderBy('sort_order')->get(['id', 'name']);

        return Inertia::render('Admin/Courses/Edit', [
            'course' => $course,
            'readinessIssues' => app(CourseReadinessService::class)->issues($course),
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, Course $course)
    {
        app(CurriculumRevisionService::class)->assertEditable($course);
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'level' => 'required|in:beginner,intermediate,advanced',
            'type' => 'required|in:self_paced,live,certified,bootcamp',
            'duration_hours' => 'nullable|numeric|min:0|max:9999.9',
            'price' => 'required|numeric|min:0|max:99999999.99',
            'currency' => 'nullable|string|size:3',
            'discount_price' => 'nullable|numeric|min:0|lte:price',
            'learning_outcomes' => 'nullable|array',
            'prerequisites' => 'nullable|array',
            'tags' => 'nullable|array',
            'thumbnail' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('thumbnail')) {
            $validated['thumbnail'] = $request->file('thumbnail')->store('courses/thumbnails', 'public');
        }

        $course->update($validated);

        return redirect()->route('admin.courses.edit', $course)
            ->with('success', 'Course updated successfully.');
    }

    public function destroy(Course $course)
    {
        app(CurriculumRevisionService::class)->assertEditable($course);
        abort_if($course->enrollments()->exists(), 409, 'Courses with learner records must be retained.');
        $course->delete();

        return redirect()->route('admin.courses.index')
            ->with('success', 'Course deleted successfully.');
    }

    public function publish(Course $course)
    {
        app(CourseReadinessService::class)->assertReady($course);
        if ($course->created_by_role === 'facilitator' && $course->approval_status !== 'approved') {
            return back()->withErrors(['readiness' => 'Approve this facilitator course before publishing.']);
        }
        app(ContentWorkflowService::class)->publish($course, auth()->user());

        return back()->with('success', 'Course published successfully.');
    }

    public function unpublish(Course $course)
    {
        $course->update(['is_published' => false]);

        return back()->with('success', 'Course unpublished.');
    }

    /*
    |--------------------------------------------------------------------------
    | Module Management
    |--------------------------------------------------------------------------
    */

    public function storeModule(Request $request, Course $course)
    {
        app(CurriculumRevisionService::class)->assertEditable($course);
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'duration_minutes' => 'nullable|integer|min:0',
        ]);

        $validated['sort_order'] = $course->modules()->count() + 1;

        $course->modules()->create($validated);

        return back()->with('success', 'Module added successfully.');
    }

    public function updateModule(Request $request, Module $module)
    {
        app(CurriculumRevisionService::class)->assertEditable($module->course);
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'duration_minutes' => 'nullable|integer|min:0',
            'is_published' => 'boolean',
        ]);

        $module->update($validated);

        return back()->with('success', 'Module updated.');
    }

    public function destroyModule(Module $module)
    {
        app(CurriculumRevisionService::class)->assertEditable($module->course);
        $module->delete();

        return back()->with('success', 'Module deleted.');
    }

    public function reorderModules(Request $request, Course $course)
    {
        app(CurriculumRevisionService::class)->assertEditable($course);
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:modules,id',
        ]);

        foreach ($validated['order'] as $position => $id) {
            Module::where('id', $id)->where('course_id', $course->id)->firstOrFail()->update(['sort_order' => $position + 1]);
        }

        return back()->with('success', 'Modules reordered.');
    }

    /*
    |--------------------------------------------------------------------------
    | Lesson Management
    |--------------------------------------------------------------------------
    */

    public function createLesson(Request $request, Module $module)
    {
        $module->load(['course.modules' => fn ($q) => $q->orderBy('sort_order'), 'course.modules.lessons' => fn ($q) => $q->orderBy('sort_order')]);

        return Inertia::render('Admin/Courses/Lessons/Create', [
            'module' => $module,
            'course' => $module->course,
            'initialType' => in_array($request->query('type'), Lesson::CONTENT_TYPES, true) ? $request->query('type') : 'text',
            'questionBank' => app(LessonActivityService::class)->questionBank($module->course),
        ]);
    }

    public function storeLesson(Request $request, Module $module)
    {
        app(CurriculumRevisionService::class)->assertEditable($module->course);
        $lesson = app(LessonAuthoringService::class)->persist($module, null, $this->validateLesson($request), $request->user());

        if ($request->boolean('stay_in_editor')) {
            return redirect()->route('admin.lessons.edit', $lesson)->with('success', 'Lesson saved.');
        }

        return redirect()->route('admin.courses.edit', $module->course_id)
            ->with('success', 'Lesson added successfully.');
    }

    public function editLesson(Lesson $lesson)
    {
        $lesson->load(['module.course.modules' => fn ($q) => $q->orderBy('sort_order'), 'module.course.modules.lessons' => fn ($q) => $q->orderBy('sort_order')]);
        $activities = app(LessonActivityService::class);

        return Inertia::render('Admin/Courses/Lessons/Edit', [
            'lesson' => $lesson,
            'module' => $lesson->module,
            'course' => $lesson->module->course,
            'activity' => $activities->editorPayload($lesson),
            'questionBank' => $activities->questionBank($lesson->module->course),
        ]);
    }

    public function updateLesson(Request $request, Lesson $lesson)
    {
        app(CurriculumRevisionService::class)->assertEditable($lesson->module->course);
        app(LessonAuthoringService::class)->persist($lesson->module, $lesson, $this->validateLesson($request, true), $request->user());

        if ($request->boolean('stay_in_editor')) {
            return redirect()->route('admin.lessons.edit', $lesson)->with('success', 'Lesson saved.');
        }

        return redirect()->route('admin.courses.edit', $lesson->module->course_id)
            ->with('success', 'Lesson updated.');
    }

    public function destroyLesson(Lesson $lesson)
    {
        app(CurriculumRevisionService::class)->assertEditable($lesson->module->course);
        $courseId = $lesson->module->course_id;
        $lesson->delete();

        return redirect()->route('admin.courses.edit', $courseId)
            ->with('success', 'Lesson deleted.');
    }

    public function reorderLessons(Request $request, Module $module)
    {
        app(CurriculumRevisionService::class)->assertEditable($module->course);
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:lessons,id',
        ]);

        foreach ($validated['order'] as $position => $id) {
            Lesson::where('id', $id)->where('module_id', $module->id)->firstOrFail()->update(['sort_order' => $position + 1]);
        }

        return back()->with('success', 'Lessons reordered.');
    }

    /*
    |--------------------------------------------------------------------------
    | Resource Management
    |--------------------------------------------------------------------------
    */

    public function storeResource(Request $request, Course $course)
    {
        app(CurriculumRevisionService::class)->assertEditable($course);
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:template,checklist,cheat_sheet,formula_sheet,case_study,playbook',
            'description' => 'nullable|string',
            'file' => 'required|file|max:20480', // 20MB
        ]);

        $file = $request->file('file');
        $asset = app(PrivateMediaService::class)->document($file, $request->user());

        $course->resources()->create([
            'title' => $validated['title'],
            'type' => $validated['type'],
            'description' => $validated['description'] ?? null,
            'file_url' => $asset->url(),
            'file_type' => $asset->mime,
            'file_size_bytes' => $file->getSize(),
            'sort_order' => $course->resources()->count() + 1,
        ]);

        return back()->with('success', 'Resource uploaded.');
    }

    public function destroyResource(Resource $resource)
    {
        app(CurriculumRevisionService::class)->assertEditable($resource->course);
        $resource->delete();

        return back()->with('success', 'Resource deleted.');
    }

    private function validateLesson(Request $request, bool $isUpdate = false): array
    {
        return app(LessonAuthoringService::class)->validate($request, $isUpdate);
    }
}
