<?php

namespace App\Http\Controllers\Admin;

use App\Models\Course;
use App\Models\MockExam;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
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
            'mocks'   => $mocks,
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
            'title'                   => 'required|string|max:255',
            'description'             => 'nullable|string',
            'course_id'               => 'required|exists:courses,id',
            'type'                    => 'required|in:full_length,mini_mock,domain_focused',
            'question_count'          => 'required|integer|min:5|max:300',
            'time_limit_minutes'      => 'required|integer|min:5',
            'passing_score_pct'       => 'required|integer|min:1|max:100',
            'domain_distribution'     => 'nullable|array',
            'difficulty_distribution' => 'nullable|array',
            'is_exam_simulation'      => 'boolean',
            'allow_pause'             => 'boolean',
            'is_published'            => 'boolean',
        ]);

        MockExam::create($validated);

        return redirect()->route('admin.mock-exams.index')
            ->with('success', 'Mock exam created.');
    }

    public function edit(MockExam $mockExam)
    {
        $courses = Course::with('modules:id,course_id,title')->orderBy('title')->get(['id', 'title']);

        return Inertia::render('Admin/MockExams/Edit', [
            'mockExam' => $mockExam,
            'courses'  => $courses,
        ]);
    }

    public function update(Request $request, MockExam $mockExam)
    {
        $validated = $request->validate([
            'title'                   => 'required|string|max:255',
            'description'             => 'nullable|string',
            'course_id'               => 'required|exists:courses,id',
            'type'                    => 'required|in:full_length,mini_mock,domain_focused',
            'question_count'          => 'required|integer|min:5|max:300',
            'time_limit_minutes'      => 'required|integer|min:5',
            'passing_score_pct'       => 'required|integer|min:1|max:100',
            'domain_distribution'     => 'nullable|array',
            'difficulty_distribution' => 'nullable|array',
            'is_exam_simulation'      => 'boolean',
            'allow_pause'             => 'boolean',
            'is_published'            => 'boolean',
        ]);

        $mockExam->update($validated);

        return redirect()->route('admin.mock-exams.index')
            ->with('success', 'Mock exam updated.');
    }

    public function destroy(MockExam $mockExam)
    {
        $mockExam->delete();

        return redirect()->route('admin.mock-exams.index')
            ->with('success', 'Mock exam deleted.');
    }

    public function publish(MockExam $mockExam)
    {
        $mockExam->update(['is_published' => true]);

        return back()->with('success', 'Mock exam published.');
    }

    public function unpublish(MockExam $mockExam)
    {
        $mockExam->update(['is_published' => false]);

        return back()->with('success', 'Mock exam unpublished.');
    }
}
