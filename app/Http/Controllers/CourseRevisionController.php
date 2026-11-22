<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Services\CurriculumRevisionService;
use Illuminate\Http\Request;

class CourseRevisionController extends Controller
{
    public function store(Request $request, Course $course, CurriculumRevisionService $revisions)
    {
        $draft = $revisions->draft($course, $request->user());
        $workspace = $request->user()->hasRole('platform_admin') ? 'admin' : 'facilitator';

        return redirect()->route($workspace.'.courses.edit', $draft)->with('success', 'Draft revision ready. Existing learners keep their current edition.');
    }

    public function recall(Request $request, Course $course, CurriculumRevisionService $revisions)
    {
        $revisions->recall($course, $request->user());

        return back()->with('success', 'Review recalled. This draft can be edited again.');
    }
}
