<?php

namespace App\Http\Controllers\Facilitator;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Course;
use App\Models\Module;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CourseController extends Controller
{
    // ─── List facilitator's own courses ────────────────────────────────────────
    public function index()
    {
        $courses = Course::where('author_id', auth()->id())
            ->with('category')
            ->latest()
            ->paginate(20);

        return Inertia::render('Facilitator/Courses/Index', [
            'courses' => $courses,
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
            'title'             => 'required|string|max:255',
            'subtitle'          => 'nullable|string|max:500',
            'description'       => 'nullable|string',
            'category_id'       => 'required|exists:categories,id',
            'level'             => 'required|in:beginner,intermediate,advanced',
            'type'              => 'required|in:self_paced,live,certified,bootcamp',
            'price'             => 'required|numeric|min:0',
            'currency'          => 'nullable|string|size:3',
            'discount_price'    => 'nullable|numeric|min:0',
            'learning_outcomes' => 'nullable|array',
            'prerequisites'     => 'nullable|array',
            'tags'              => 'nullable|array',
            'thumbnail'         => 'nullable|string|max:500',
        ]);

        $course = Course::create([
            ...$validated,
            'uuid'             => (string) Str::uuid(),
            'slug'             => $this->uniqueSlug($validated['title']),
            'author_id'        => auth()->id(),
            'created_by_role'  => 'facilitator',
            'approval_status'  => 'draft',
            'is_published'     => false,
            'version'          => 1,
        ]);

        return redirect()->route('facilitator.courses.edit', $course)
            ->with('success', 'Course created as draft. Add modules and lessons, then submit for admin approval.');
    }

    // ─── Edit form ───────────────────────────────────────────────────────────────
    public function edit(Course $course)
    {
        $this->authorizeOwnership($course);

        $course->load(['modules.lessons', 'category']);

        return Inertia::render('Facilitator/Courses/Edit', [
            'course'     => $course,
            'categories' => Category::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    // ─── Update course ────────────────────────────────────────────────────────────
    public function update(Request $request, Course $course)
    {
        $this->authorizeOwnership($course);
        $this->ensureEditable($course);

        $validated = $request->validate([
            'title'             => 'required|string|max:255',
            'subtitle'          => 'nullable|string|max:500',
            'description'       => 'nullable|string',
            'category_id'       => 'required|exists:categories,id',
            'level'             => 'required|in:beginner,intermediate,advanced',
            'type'              => 'required|in:self_paced,live,certified,bootcamp',
            'price'             => 'required|numeric|min:0',
            'currency'          => 'nullable|string|size:3',
            'discount_price'    => 'nullable|numeric|min:0',
            'learning_outcomes' => 'nullable|array',
            'prerequisites'     => 'nullable|array',
            'tags'              => 'nullable|array',
            'thumbnail'         => 'nullable|string|max:500',
            'preview_video'     => 'nullable|string|max:500',
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

        if ($course->modules()->count() === 0) {
            return back()->with('error', 'Add at least one module before submitting.');
        }

        $course->update([
            'approval_status'        => 'pending_review',
            'submitted_for_review_at' => now(),
            'rejection_reason'       => null,
        ]);

        // Notify all platform admins
        \App\Models\User::role('platform_admin')->each(function ($admin) use ($course) {
            $admin->notify(new \App\Notifications\CourseSubmittedForReview($course));
        });

        return back()->with('success', 'Course submitted for admin review. You will be notified once reviewed.');
    }

    // ─── Delete (only drafts) ─────────────────────────────────────────────────────
    public function destroy(Course $course)
    {
        $this->authorizeOwnership($course);

        if ($course->approval_status !== 'draft') {
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
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $module = $course->modules()->create([
            ...$validated,
            'sort_order'   => $course->modules()->count(),
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
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'sort_order'  => 'nullable|integer',
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
    public function storeLesson(Request $request, Course $course, Module $module)
    {
        $this->authorizeOwnership($course);
        $this->ensureEditable($course);
        abort_unless($module->course_id === $course->id, 403);

        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'content_type'     => 'required|in:text,video,audio,pdf,slides,mixed',
            'text_content'     => 'nullable|string',
            'video_url'        => 'nullable|url|max:500',
            'audio_url'        => 'nullable|url|max:500',
            'pdf_url'          => 'nullable|url|max:500',
            'duration_minutes' => 'nullable|integer|min:1',
            'is_free_preview'  => 'boolean',
        ]);

        $lesson = $module->lessons()->create([
            ...$validated,
            'sort_order'   => $module->lessons()->count(),
            'is_published' => true,
        ]);

        return back()->with(['success' => 'Lesson added.', 'lesson' => $lesson]);
    }

    public function updateLesson(Request $request, Course $course, Module $module, Lesson $lesson)
    {
        $this->authorizeOwnership($course);
        $this->ensureEditable($course);
        abort_unless($module->course_id === $course->id && $lesson->module_id === $module->id, 403);

        $lesson->update($request->validate([
            'title'            => 'required|string|max:255',
            'content_type'     => 'required|in:text,video,audio,pdf,slides,mixed',
            'text_content'     => 'nullable|string',
            'video_url'        => 'nullable|url|max:500',
            'audio_url'        => 'nullable|url|max:500',
            'pdf_url'          => 'nullable|url|max:500',
            'duration_minutes' => 'nullable|integer|min:1',
            'is_free_preview'  => 'boolean',
            'sort_order'       => 'nullable|integer',
        ]));

        return back()->with('success', 'Lesson updated.');
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
        if ($course->approval_status === 'pending_review') {
            abort(403, 'Course is under review and cannot be edited. Recall it first.');
        }
        if ($course->approval_status === 'approved' && $course->is_published) {
            abort(403, 'Published courses cannot be edited directly. Create a new version.');
        }
    }

    protected function uniqueSlug(string $title): string
    {
        $slug = Str::slug($title);
        $count = Course::where('slug', 'like', $slug . '%')->count();
        return $count > 0 ? $slug . '-' . ($count + 1) : $slug;
    }
}
