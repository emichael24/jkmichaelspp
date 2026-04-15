<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Course;
use App\Models\Module;
use App\Models\Lesson;
use App\Models\Resource;
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
            'courses'    => $courses,
            'categories' => $categories,
            'filters'    => $request->only(['search', 'category', 'level', 'type', 'status']),
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
            'title'             => 'required|string|max:255',
            'subtitle'          => 'nullable|string|max:255',
            'description'       => 'nullable|string',
            'category_id'       => 'required|exists:categories,id',
            'level'             => 'required|in:beginner,intermediate,advanced,expert',
            'type'              => 'required|in:free,premium,certified,enterprise',
            'duration_hours'    => 'nullable|numeric|min:0',
            'price'             => 'nullable|numeric|min:0',
            'currency'          => 'nullable|string|size:3',
            'discount_price'    => 'nullable|numeric|min:0',
            'learning_outcomes' => 'nullable|array',
            'prerequisites'     => 'nullable|array',
            'tags'              => 'nullable|array',
            'thumbnail'         => 'nullable|image|max:2048',
        ]);

        // Handle thumbnail upload
        if ($request->hasFile('thumbnail')) {
            $validated['thumbnail'] = $request->file('thumbnail')->store('courses/thumbnails', 'public');
        }

        $validated['author_id'] = Auth::id();

        $course = Course::create($validated);

        return redirect()->route('admin.courses.edit', $course)
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
            'course'     => $course,
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, Course $course)
    {
        $validated = $request->validate([
            'title'             => 'required|string|max:255',
            'subtitle'          => 'nullable|string|max:255',
            'description'       => 'nullable|string',
            'category_id'       => 'required|exists:categories,id',
            'level'             => 'required|in:beginner,intermediate,advanced,expert',
            'type'              => 'required|in:free,premium,certified,enterprise',
            'duration_hours'    => 'nullable|numeric|min:0',
            'price'             => 'nullable|numeric|min:0',
            'currency'          => 'nullable|string|size:3',
            'discount_price'    => 'nullable|numeric|min:0',
            'learning_outcomes' => 'nullable|array',
            'prerequisites'     => 'nullable|array',
            'tags'              => 'nullable|array',
            'thumbnail'         => 'nullable|image|max:2048',
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
        $course->delete();

        return redirect()->route('admin.courses.index')
            ->with('success', 'Course deleted successfully.');
    }

    public function publish(Course $course)
    {
        $course->update([
            'is_published'  => true,
            'published_at'  => now(),
        ]);

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
        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'description'      => 'nullable|string',
            'duration_minutes' => 'nullable|integer|min:0',
        ]);

        $validated['sort_order'] = $course->modules()->count() + 1;

        $course->modules()->create($validated);

        return back()->with('success', 'Module added successfully.');
    }

    public function updateModule(Request $request, Module $module)
    {
        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'description'      => 'nullable|string',
            'duration_minutes' => 'nullable|integer|min:0',
            'is_published'     => 'boolean',
        ]);

        $module->update($validated);

        return back()->with('success', 'Module updated.');
    }

    public function destroyModule(Module $module)
    {
        $module->delete();

        return back()->with('success', 'Module deleted.');
    }

    public function reorderModules(Request $request, Course $course)
    {
        $validated = $request->validate([
            'order'   => 'required|array',
            'order.*' => 'integer|exists:modules,id',
        ]);

        foreach ($validated['order'] as $position => $id) {
            Module::where('id', $id)->where('course_id', $course->id)
                ->update(['sort_order' => $position + 1]);
        }

        return back()->with('success', 'Modules reordered.');
    }

    /*
    |--------------------------------------------------------------------------
    | Lesson Management
    |--------------------------------------------------------------------------
    */

    public function createLesson(Module $module)
    {
        $module->load('course');

        return Inertia::render('Admin/Courses/Lessons/Create', [
            'module' => $module,
            'course' => $module->course,
        ]);
    }

    public function storeLesson(Request $request, Module $module)
    {
        $validated = $this->validateLesson($request);

        $validated['sort_order'] = $module->lessons()->count() + 1;

        $module->lessons()->create($validated);

        return redirect()->route('admin.courses.edit', $module->course_id)
            ->with('success', 'Lesson added successfully.');
    }

    public function editLesson(Lesson $lesson)
    {
        $lesson->load('module.course');

        return Inertia::render('Admin/Courses/Lessons/Edit', [
            'lesson' => $lesson,
            'module' => $lesson->module,
            'course' => $lesson->module->course,
        ]);
    }

    public function updateLesson(Request $request, Lesson $lesson)
    {
        $validated = $this->validateLesson($request, true);

        $lesson->update($validated);

        return redirect()->route('admin.courses.edit', $lesson->module->course_id)
            ->with('success', 'Lesson updated.');
    }

    public function destroyLesson(Lesson $lesson)
    {
        $courseId = $lesson->module->course_id;
        $lesson->delete();

        return redirect()->route('admin.courses.edit', $courseId)
            ->with('success', 'Lesson deleted.');
    }

    public function reorderLessons(Request $request, Module $module)
    {
        $validated = $request->validate([
            'order'   => 'required|array',
            'order.*' => 'integer|exists:lessons,id',
        ]);

        foreach ($validated['order'] as $position => $id) {
            Lesson::where('id', $id)->where('module_id', $module->id)
                ->update(['sort_order' => $position + 1]);
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
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'type'        => 'required|in:template,checklist,cheat_sheet,formula_sheet,case_study,playbook',
            'description' => 'nullable|string',
            'file'        => 'required|file|max:20480', // 20MB
        ]);

        $file = $request->file('file');
        $path = $file->store('courses/resources', 'public');

        $course->resources()->create([
            'title'           => $validated['title'],
            'type'            => $validated['type'],
            'description'     => $validated['description'] ?? null,
            'file_url'        => $path,
            'file_type'       => $file->getClientMimeType(),
            'file_size_bytes' => $file->getSize(),
            'sort_order'      => $course->resources()->count() + 1,
        ]);

        return back()->with('success', 'Resource uploaded.');
    }

    public function destroyResource(Resource $resource)
    {
        $resource->delete();

        return back()->with('success', 'Resource deleted.');
    }

    private function validateLesson(Request $request, bool $isUpdate = false): array
    {
        $validated = $request->validate([
            'title'                       => 'required|string|max:255',
            'content_type'                => 'required|in:text,video,audio,pdf,slides,mixed',
            'text_content'                => 'nullable|string',
            'summary'                     => 'nullable|string',
            'key_terms'                   => 'nullable|array',
            'video_url'                   => 'nullable|url',
            'video_duration_seconds'      => 'nullable|integer|min:0',
            'video_transcript'            => 'nullable|string',
            'audio_url'                   => 'nullable|url',
            'audio_duration_seconds'      => 'nullable|integer|min:0',
            'pdf_url'                     => 'nullable|string',
            'slides_url'                  => 'nullable|string',
            'scorm_path'                  => 'nullable|string|max:500',
            'scorm_version'               => 'nullable|in:1.2,2004',
            'duration_minutes'            => 'nullable|integer|min:0',
            'is_free_preview'             => 'boolean',
            'is_downloadable'             => 'boolean',
            'unlock_type'                 => 'nullable|in:immediate,date,days_after_enrollment',
            'unlock_at'                   => 'nullable|date',
            'unlock_days_after_enrollment' => 'nullable|integer|min:1|max:365',
            'is_published'                => $isUpdate ? 'boolean' : 'sometimes|boolean',
        ]);

        $validated['unlock_type'] = $validated['unlock_type'] ?? 'immediate';

        if ($validated['unlock_type'] !== 'date') {
            $validated['unlock_at'] = null;
        }

        if ($validated['unlock_type'] !== 'days_after_enrollment') {
            $validated['unlock_days_after_enrollment'] = null;
        }

        return $validated;
    }
}
