<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\CoursePrerequisiteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class LearningRuleController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['search' => 'nullable|string|max:100']);
        $courses = Course::select('id', 'title', 'is_published')->when($filters['search'] ?? null, fn ($q, $search) => $q->where('title', 'like', '%'.$search.'%'))->orderBy('title')->paginate(30)->withQueryString();
        $rules = DB::table('course_prerequisites')->whereIn('course_id', $courses->getCollection()->pluck('id'))->get()->groupBy('course_id');
        $courses->getCollection()->transform(function ($course) use ($rules) {
            $course->prerequisite_ids = ($rules[$course->id] ?? collect())->pluck('prerequisite_id')->all();

            return $course;
        });

        return Inertia::render('Admin/LearningRules', ['courses' => $courses, 'filters' => $filters,
            'choices' => Course::published()->orderBy('title')->get(['id', 'title'])]);
    }

    public function update(Request $request, Course $course, CoursePrerequisiteService $rules)
    {
        $data = $request->validate(['prerequisite_ids' => 'present|array|max:50', 'prerequisite_ids.*' => 'required|integer|distinct']);
        $rules->replace($course, $data['prerequisite_ids']);

        return back()->with('success', 'Prerequisites saved. Learning access now follows these requirements.');
    }
}
