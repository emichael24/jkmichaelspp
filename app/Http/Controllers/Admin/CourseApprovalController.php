<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentApproval;
use App\Models\Course;
use App\Notifications\CourseApproved;
use App\Notifications\CourseRejected;
use App\Services\ContentWorkflowService;
use App\Services\CourseReadinessService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CourseApprovalController extends Controller
{
    /**
     * List all courses submitted for review.
     */
    public function index(Request $request)
    {
        $query = Course::with(['author', 'category'])
            ->where('created_by_role', 'facilitator');

        if ($status = $request->get('status')) {
            $query->where('approval_status', $status);
        } else {
            $query->where('approval_status', 'pending_review');
        }

        $courses = $query->latest('submitted_for_review_at')->paginate(20);

        $counts = [
            'pending' => Course::where('created_by_role', 'facilitator')->where('approval_status', 'pending_review')->count(),
            'approved' => Course::where('created_by_role', 'facilitator')->where('approval_status', 'approved')->count(),
            'rejected' => Course::where('created_by_role', 'facilitator')->where('approval_status', 'rejected')->count(),
            'draft' => Course::where('created_by_role', 'facilitator')->where('approval_status', 'draft')->count(),
        ];

        return Inertia::render('Admin/CourseApproval/Index', [
            'courses' => $courses,
            'counts' => $counts,
            'currentStatus' => $request->get('status', 'pending_review'),
        ]);
    }

    /**
     * Show the review form for a specific course.
     */
    public function show(Course $course)
    {
        $course->load(['author', 'category', 'modules.lessons', 'approvedBy']);

        return Inertia::render('Admin/CourseApproval/Review', [
            'course' => $course,
        ]);
    }

    /**
     * Approve a course — mark as approved and optionally publish immediately.
     */
    public function approve(Request $request, Course $course)
    {
        $request->validate([
            'publish_immediately' => 'boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($course->approval_status !== 'pending_review') {
            return back()->with('error', 'This course is not pending review.');
        }

        $publishNow = $request->boolean('publish_immediately', true);

        abort_if($course->author_id === auth()->id(), 403, 'A different reviewer must approve this course.');
        app(CourseReadinessService::class)->assertReady($course);

        $approval = ContentApproval::forContent(Course::class, $course->id)->pending()->latest('id')->firstOrFail();
        app(ContentWorkflowService::class)->approve($approval, $request->user(), $request->input('notes'), $publishNow);

        // Notify the facilitator
        $course->author->notify(new CourseApproved($course, $request->input('notes')));

        return redirect()->route('admin.course-approvals.index')
            ->with('success', "Course \"{$course->title}\" approved".($publishNow ? ' and published' : '').'.');
    }

    /**
     * Reject a course with a reason.
     */
    public function reject(Request $request, Course $course)
    {
        $request->validate([
            'rejection_reason' => 'required|string|min:10|max:2000',
        ]);

        if ($course->approval_status !== 'pending_review') {
            return back()->with('error', 'This course is not pending review.');
        }

        $approval = ContentApproval::forContent(Course::class, $course->id)->pending()->latest('id')->firstOrFail();
        app(ContentWorkflowService::class)->reject($approval, $request->user(), $request->input('rejection_reason'));

        // Notify the facilitator with the reason
        $course->author->notify(new CourseRejected($course, $request->input('rejection_reason')));

        return redirect()->route('admin.course-approvals.index')
            ->with('success', "Course \"{$course->title}\" rejected. The facilitator has been notified.");
    }

    /**
     * Unpublish an already-approved course (e.g., content issues found after approval).
     */
    public function unpublish(Course $course)
    {
        $course->update([
            'is_published' => false,
        ]);

        return back()->with('success', 'Course unpublished.');
    }
}
