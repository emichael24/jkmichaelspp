<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Question;
use Illuminate\Http\Request;
use Inertia\Inertia;

class QuestionController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Question Bank CRUD
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $questions = Question::with('course:id,title')
            ->withCount('responses')
            ->when($request->search, fn ($q, $s) => $q->where('stem', 'like', "%{$s}%"))
            ->when($request->course_id, fn ($q, $c) => $q->forCourse($c))
            ->when($request->domain, fn ($q, $d) => $q->byDomain($d))
            ->when($request->difficulty, fn ($q, $d) => $q->byDifficulty($d))
            ->when($request->type, fn ($q, $t) => $q->byType($t))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $courses = Course::orderBy('title')->get(['id', 'title']);

        // Aggregate stats for the top summary bar
        $stats = [
            'total'     => Question::count(),
            'published' => Question::published()->count(),
            'draft'     => Question::where('status', 'draft')->count(),
            'review'    => Question::where('status', 'in_review')->count(),
        ];

        // Distinct domains from DB
        $domains = Question::select('domain')
            ->distinct()
            ->whereNotNull('domain')
            ->orderBy('domain')
            ->pluck('domain');

        return Inertia::render('Admin/Questions/Index', [
            'questions' => $questions,
            'courses'   => $courses,
            'domains'   => $domains,
            'stats'     => $stats,
            'filters'   => $request->only(['search', 'course_id', 'domain', 'difficulty', 'type', 'status']),
        ]);
    }

    public function create(Request $request)
    {
        $courses = Course::orderBy('title')->get(['id', 'title']);

        // Optionally pre-select course
        $courseId = $request->query('course_id');
        $lessons = $courseId
            ? Lesson::whereHas('module', fn ($q) => $q->where('course_id', $courseId))
                ->orderBy('sort_order')
                ->get(['id', 'title', 'module_id'])
            : collect();

        return Inertia::render('Admin/Questions/Create', [
            'courses'          => $courses,
            'lessons'          => $lessons,
            'preselectedCourse' => $courseId ? (int) $courseId : null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id'               => 'required|exists:courses,id',
            'domain'                  => 'nullable|string|max:255',
            'subtopic'                => 'nullable|string|max:255',
            'type'                    => 'required|in:single_choice,multiple_choice,true_false,fill_blank,ordering,matching,hotspot,drag_drop,case_study',
            'difficulty'              => 'required|in:easy,medium,hard,expert',
            'bloom_level'             => 'nullable|in:remember,understand,apply,analyze,evaluate,create',
            'stem'                    => 'required|string',
            'stem_media'              => 'nullable|array',
            'options'                 => 'required|array|min:2',
            'options.*.id'            => 'required|string',
            'options.*.text'          => 'required|string',
            'options.*.media_url'     => 'nullable|string',
            'correct_answer_ids'      => 'required|array|min:1',
            'correct_answer_ids.*'    => 'string',
            'explanation_correct'     => 'nullable|string',
            'explanation_incorrect'   => 'nullable|array',
            'exam_tip'               => 'nullable|string',
            'linked_lesson_id'        => 'nullable|exists:lessons,id',
            'time_estimate_seconds'   => 'nullable|integer|min:0',
            'tags'                    => 'nullable|array',
            'status'                  => 'required|in:draft,in_review,published,retired',
        ]);

        $question = Question::create($validated);

        return redirect()
            ->route('admin.questions.index')
            ->with('success', "Question #{$question->id} created successfully.");
    }

    public function edit(Question $question)
    {
        $courses = Course::orderBy('title')->get(['id', 'title']);

        $lessons = Lesson::whereHas('module', fn ($q) => $q->where('course_id', $question->course_id))
            ->orderBy('sort_order')
            ->get(['id', 'title', 'module_id']);

        return Inertia::render('Admin/Questions/Edit', [
            'question' => $question,
            'courses'  => $courses,
            'lessons'  => $lessons,
        ]);
    }

    public function update(Request $request, Question $question)
    {
        $validated = $request->validate([
            'course_id'               => 'required|exists:courses,id',
            'domain'                  => 'nullable|string|max:255',
            'subtopic'                => 'nullable|string|max:255',
            'type'                    => 'required|in:single_choice,multiple_choice,true_false,fill_blank,ordering,matching,hotspot,drag_drop,case_study',
            'difficulty'              => 'required|in:easy,medium,hard,expert',
            'bloom_level'             => 'nullable|in:remember,understand,apply,analyze,evaluate,create',
            'stem'                    => 'required|string',
            'stem_media'              => 'nullable|array',
            'options'                 => 'required|array|min:2',
            'options.*.id'            => 'required|string',
            'options.*.text'          => 'required|string',
            'options.*.media_url'     => 'nullable|string',
            'correct_answer_ids'      => 'required|array|min:1',
            'correct_answer_ids.*'    => 'string',
            'explanation_correct'     => 'nullable|string',
            'explanation_incorrect'   => 'nullable|array',
            'exam_tip'               => 'nullable|string',
            'linked_lesson_id'        => 'nullable|exists:lessons,id',
            'time_estimate_seconds'   => 'nullable|integer|min:0',
            'tags'                    => 'nullable|array',
            'status'                  => 'required|in:draft,in_review,published,retired',
        ]);

        $question->update($validated);

        return redirect()
            ->route('admin.questions.index')
            ->with('success', 'Question updated successfully.');
    }

    public function destroy(Question $question)
    {
        $question->delete(); // soft-delete

        return redirect()
            ->route('admin.questions.index')
            ->with('success', 'Question deleted.');
    }

    /**
     * API: get lessons for a course (used in question editor).
     */
    public function lessonsForCourse(Course $course)
    {
        $lessons = Lesson::whereHas('module', fn ($q) => $q->where('course_id', $course->id))
            ->orderBy('sort_order')
            ->get(['id', 'title', 'module_id']);

        return response()->json($lessons);
    }
}
