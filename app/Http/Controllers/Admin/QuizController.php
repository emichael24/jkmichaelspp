<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Http\Request;
use Inertia\Inertia;

class QuizController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Quiz Builder CRUD
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $quizzes = Quiz::with('course:id,title,slug')
            ->withCount(['questions', 'attempts'])
            ->when($request->search, fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->when($request->course_id, fn ($q, $c) => $q->where('course_id', $c))
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->status === 'published', fn ($q) => $q->published())
            ->when($request->status === 'draft', fn ($q) => $q->where('is_published', false))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $courses = Course::orderBy('title')->get(['id', 'title']);

        return Inertia::render('Admin/Quizzes/Index', [
            'quizzes' => $quizzes,
            'courses' => $courses,
            'filters' => $request->only(['search', 'course_id', 'type', 'status']),
        ]);
    }

    public function create(Request $request)
    {
        $courses = Course::with('modules:id,course_id,title', 'modules.lessons:id,module_id,title')
            ->orderBy('title')
            ->get(['id', 'title']);

        return Inertia::render('Admin/Quizzes/Create', [
            'courses'           => $courses,
            'preselectedCourse' => $request->query('course_id') ? (int) $request->query('course_id') : null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'              => 'required|string|max:255',
            'course_id'          => 'required|exists:courses,id',
            'module_id'          => 'nullable|exists:modules,id',
            'lesson_id'          => 'nullable|exists:lessons,id',
            'type'               => 'required|in:topic_quiz,module_test,practice_exam,diagnostic,certification_prep',
            'question_count'     => 'required|integer|min:1|max:200',
            'time_limit_minutes' => 'nullable|integer|min:1',
            'passing_score_pct'  => 'required|integer|min:1|max:100',
            'is_randomized'      => 'boolean',
            'max_attempts'       => 'nullable|integer|min:1',
            'is_published'       => 'boolean',
        ]);

        $quiz = Quiz::create($validated);

        return redirect()
            ->route('admin.quizzes.edit', $quiz)
            ->with('success', 'Quiz created. Now add questions below.');
    }

    public function edit(Quiz $quiz)
    {
        $quiz->load([
            'course:id,title',
            'module:id,title',
            'lesson:id,title',
            'questions' => fn ($q) => $q->orderBy('quiz_questions.sort_order'),
        ]);
        $quiz->loadCount('attempts');

        $courses = Course::with('modules:id,course_id,title', 'modules.lessons:id,module_id,title')
            ->orderBy('title')
            ->get(['id', 'title']);

        // Available questions for this course (not yet assigned to this quiz)
        $assignedIds = $quiz->questions->pluck('id');
        $availableQuestions = Question::forCourse($quiz->course_id)
            ->published()
            ->whereNotIn('id', $assignedIds)
            ->orderBy('domain')
            ->orderBy('difficulty')
            ->get(['id', 'uuid', 'stem', 'type', 'domain', 'difficulty', 'bloom_level']);

        return Inertia::render('Admin/Quizzes/Edit', [
            'quiz'               => $quiz,
            'courses'            => $courses,
            'availableQuestions' => $availableQuestions,
        ]);
    }

    public function update(Request $request, Quiz $quiz)
    {
        $validated = $request->validate([
            'title'              => 'required|string|max:255',
            'course_id'          => 'required|exists:courses,id',
            'module_id'          => 'nullable|exists:modules,id',
            'lesson_id'          => 'nullable|exists:lessons,id',
            'type'               => 'required|in:topic_quiz,module_test,practice_exam,diagnostic,certification_prep',
            'question_count'     => 'required|integer|min:1|max:200',
            'time_limit_minutes' => 'nullable|integer|min:1',
            'passing_score_pct'  => 'required|integer|min:1|max:100',
            'is_randomized'      => 'boolean',
            'max_attempts'       => 'nullable|integer|min:1',
            'is_published'       => 'boolean',
        ]);

        $quiz->update($validated);

        return redirect()
            ->route('admin.quizzes.edit', $quiz)
            ->with('success', 'Quiz updated successfully.');
    }

    public function destroy(Quiz $quiz)
    {
        $quiz->questions()->detach();
        $quiz->delete();

        return redirect()
            ->route('admin.quizzes.index')
            ->with('success', 'Quiz deleted.');
    }

    /*
    |--------------------------------------------------------------------------
    | Quiz ↔ Question Assignment
    |--------------------------------------------------------------------------
    */

    /**
     * Add questions to a quiz.
     */
    public function addQuestions(Request $request, Quiz $quiz)
    {
        $request->validate([
            'question_ids'   => 'required|array|min:1',
            'question_ids.*' => 'exists:questions,id',
        ]);

        $currentMax = $quiz->questions()->max('quiz_questions.sort_order') ?? 0;

        foreach ($request->question_ids as $i => $qid) {
            $quiz->questions()->attach($qid, ['sort_order' => $currentMax + $i + 1]);
        }

        return back()->with('success', count($request->question_ids) . ' question(s) added.');
    }

    /**
     * Remove a question from a quiz.
     */
    public function removeQuestion(Quiz $quiz, Question $question)
    {
        $quiz->questions()->detach($question->id);

        return back()->with('success', 'Question removed from quiz.');
    }

    /**
     * Reorder questions in a quiz.
     */
    public function reorderQuestions(Request $request, Quiz $quiz)
    {
        $request->validate([
            'order'   => 'required|array',
            'order.*' => 'integer|exists:questions,id',
        ]);

        foreach ($request->order as $index => $questionId) {
            $quiz->questions()->updateExistingPivot($questionId, ['sort_order' => $index + 1]);
        }

        return back()->with('success', 'Question order saved.');
    }

    /**
     * Publish / Unpublish a quiz.
     */
    public function publish(Quiz $quiz)
    {
        $quiz->update(['is_published' => true]);

        return back()->with('success', 'Quiz published.');
    }

    public function unpublish(Quiz $quiz)
    {
        $quiz->update(['is_published' => false]);

        return back()->with('success', 'Quiz unpublished.');
    }
}
