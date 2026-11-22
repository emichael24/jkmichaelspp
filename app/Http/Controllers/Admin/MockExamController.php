<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\MockExam;
use App\Services\AssessmentBlueprintService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MockExamController extends Controller
{
    public function index(Request $request)
    {
        $mocks = MockExam::with('course:id,title')
            ->withCount(['attempts', 'attempts as completed_count' => fn ($q) => $q->completed()])
            ->when($request->search, fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->when($request->course_id, fn ($q, $c) => $q->where('course_id', $c))
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->filled('is_published'), fn ($q) => $q->where('is_published', $request->boolean('is_published')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $courses = Course::orderBy('title')->get(['id', 'title']);

        return Inertia::render('Admin/MockExams/Index', [
            'mocks' => $mocks,
            'courses' => $courses,
            'filters' => $request->only(['search', 'course_id', 'type', 'is_published']),
        ]);
    }

    public function create()
    {
        $courses = Course::with('modules:id,course_id,title')->orderBy('title')->get(['id', 'title']);

        return Inertia::render('Admin/MockExams/Create', [
            'courses' => $courses,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'course_id' => 'required|exists:courses,id',
            'type' => 'required|in:full_length,mini_mock,domain_focused',
            'question_count' => 'required|integer|min:5|max:300',
            'time_limit_minutes' => 'required|integer|min:5',
            'passing_score_pct' => 'required|integer|min:1|max:100',
            'domain_distribution' => 'nullable|array',
            'difficulty_distribution' => 'nullable|array',
            'is_exam_simulation' => 'boolean',
            'allow_pause' => 'boolean',
            'is_published' => 'boolean',
        ]);

        $validated = $this->blueprint($validated);
        MockExam::create($validated);

        return redirect()->route('admin.mock-exams.index')
            ->with('success', 'Mock exam created.');
    }

    public function edit(MockExam $mockExam)
    {
        foreach (['domain_distribution', 'difficulty_distribution'] as $field) {
            $mockExam->{$field} = app(AssessmentBlueprintService::class)->counts($mockExam->{$field}, $mockExam->question_count, $field);
        }
        $courses = Course::with('modules:id,course_id,title')->orderBy('title')->get(['id', 'title']);

        return Inertia::render('Admin/MockExams/Edit', [
            'mockExam' => $mockExam,
            'courses' => $courses,
        ]);
    }

    public function update(Request $request, MockExam $mockExam)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'course_id' => 'required|exists:courses,id',
            'type' => 'required|in:full_length,mini_mock,domain_focused',
            'question_count' => 'required|integer|min:5|max:300',
            'time_limit_minutes' => 'required|integer|min:5',
            'passing_score_pct' => 'required|integer|min:1|max:100',
            'domain_distribution' => 'nullable|array',
            'difficulty_distribution' => 'nullable|array',
            'is_exam_simulation' => 'boolean',
            'allow_pause' => 'boolean',
            'is_published' => 'boolean',
        ]);

        abort_unless((int) $validated['course_id'] === $mockExam->course_id, 422, 'Create a new assessment to change its course.');
        $validated = $this->blueprint($validated);
        $mockExam->update($validated);

        return redirect()->route('admin.mock-exams.index')
            ->with('success', 'Mock exam updated.');
    }

    public function destroy(MockExam $mockExam)
    {
        abort_if($mockExam->attempts()->exists(), 409, 'Assessments with learner attempts must be retained. Unpublish the assessment instead.');
        $mockExam->delete();

        return redirect()->route('admin.mock-exams.index')
            ->with('success', 'Mock exam deleted.');
    }

    public function publish(MockExam $mockExam)
    {
        $this->blueprint(array_merge($mockExam->attributesToArray(), ['is_published' => true]), true);
        $mockExam->update(['is_published' => true]);

        return back()->with('success', 'Mock exam published.');
    }

    private function blueprint(array $values, bool $legacy = false): array
    {
        $service = app(AssessmentBlueprintService::class);
        foreach (['domain_distribution', 'difficulty_distribution'] as $field) {
            $values[$field] = $service->counts($values[$field] ?? [], (int) $values['question_count'], $field, $legacy);
        }
        if ($values['is_published'] ?? false) {
            $service->select((int) $values['course_id'], (int) $values['question_count'], $values['domain_distribution'], $values['difficulty_distribution']);
        }

        return $values;
    }

    public function unpublish(MockExam $mockExam)
    {
        $mockExam->update(['is_published' => false]);

        return back()->with('success', 'Mock exam unpublished.');
    }
}
