<?php

namespace App\Http\Controllers\Facilitator;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AssignmentSubmission;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class GradingController extends Controller
{
    /**
     * Show the Grading Dashboard for Facilitators.
     */
    public function index()
    {
        // Only fetch submissions relevant to the courses this facilitator manages
        // For simplicity, we assume they can see all pending submissions here.
        $submissions = AssignmentSubmission::with(['user', 'assignment.course'])
            ->where('status', 'submitted') // Looking for ungraded
            ->orderBy('submitted_at', 'asc')
            ->paginate(15);

        return Inertia::render('Facilitator/GradingDashboard', [
            'submissions' => $submissions
        ]);
    }

    /**
     * Post a grade and feedback to a submission.
     */
    public function storeGrade(Request $request, $id)
    {
        $request->validate([
            'score' => 'required|numeric|min:0|max:100',
            'feedback' => 'nullable|string|max:1000'
        ]);

        $submission = AssignmentSubmission::findOrFail($id);

        $submission->update([
            'status' => 'graded',
            'score' => $request->score,
            'feedback' => $request->feedback,
            'graded_by' => Auth::id(),
            'graded_at' => now(),
        ]);

        // Dispatch a Notification to the learner
        // \App\Models\Notification::create([...]);

        return redirect()->back()->with('success', 'Assignment graded successfully.');
    }
}

