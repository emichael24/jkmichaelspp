<?php

namespace App\Http\Controllers;

use App\Models\AssignmentSubmission;
use App\Models\Lesson;
use App\Services\LearningAccessService;
use App\Services\LessonActivityService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class LessonActivityController extends Controller
{
    /** Learner submits an assignment or case-study response from the lesson player. */
    public function submit(Request $request, Lesson $lesson, LessonActivityService $activities)
    {
        app(LearningAccessService::class)->assertLesson($lesson, $request->user());
        $data = $request->validate([
            'content' => 'nullable|string|max:60000',
            'answers' => 'nullable|array|max:30',
            'answers.*' => 'nullable|string|max:20000',
            'files' => 'nullable|array|max:5',
            'files.*' => 'file|max:20480|mimes:'.LessonActivityService::SUBMISSION_MIMES,
        ]);
        $activities->submit($lesson->load('assignment', 'module.course'), $request->user(), $data, $request->file('files', []));

        return back()->with('success', 'Your work was submitted. You can continue to the next lesson while it is reviewed.');
    }

    /** Attachments are readable by their author and by the course's graders only. */
    public function file(Request $request, AssignmentSubmission $submission, int $index, LessonActivityService $activities)
    {
        $submission->load('assignment.course');
        abort_unless($submission->user_id === $request->user()->id || $activities->canGrade($request->user(), $submission->assignment), 403);
        $file = array_values($submission->file_urls ?? [])[$index] ?? null;
        abort_unless($file && ! empty($file['path']) && str_starts_with($file['path'], 'assignment-submissions/') && Storage::disk('local')->exists($file['path']), 404);

        return Storage::disk('local')->download($file['path'], $file['name'] ?? basename($file['path']), [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Grading queue for one assignment or case-study lesson. */
    public function submissions(Request $request, Lesson $lesson, LessonActivityService $activities)
    {
        $lesson->load('assignment', 'module.course');
        abort_unless($lesson->assignment && $activities->canGrade($request->user(), $lesson->assignment), 404);
        $course = $lesson->module->course;
        $submissions = $lesson->assignment->submissions()->with('user:id,first_name,last_name,email')
            ->orderByRaw("case when status in ('submitted','resubmitted','in_review') then 0 else 1 end")
            ->latest('submitted_at')->paginate(25)->withQueryString();
        $submissions->getCollection()->transform(fn (AssignmentSubmission $submission) => [
            ...$activities->submissionPayload($submission),
            'learner' => ['id' => $submission->user->id, 'name' => trim($submission->user->first_name.' '.$submission->user->last_name), 'email' => $submission->user->email],
        ]);
        $workspace = $request->user()->hasRole('platform_admin') ? 'admin' : 'facilitator';

        return Inertia::render('Authoring/Submissions', [
            'lesson' => ['id' => $lesson->id, 'title' => $lesson->title, 'content_type' => $lesson->content_type],
            'course' => ['id' => $course->id, 'title' => $course->title],
            'assignment' => ['max_score' => $lesson->assignment->max_score, 'passing_score' => $lesson->assignment->passing_score, 'prompts' => $lesson->assignment->prompts ?? []],
            'submissions' => $submissions,
            'backUrl' => $workspace === 'admin' ? "/admin/lessons/{$lesson->id}/edit" : "/facilitator/courses/{$course->id}/modules/{$lesson->module_id}/lessons/{$lesson->id}/edit",
            'courseUrl' => "/{$workspace}/courses/{$course->id}/edit?tab=curriculum",
        ]);
    }

    public function grade(Request $request, AssignmentSubmission $submission, LessonActivityService $activities)
    {
        $submission->load('assignment.course');
        abort_unless($activities->canGrade($request->user(), $submission->assignment), 403);
        $data = $request->validate([
            'score' => 'required|integer|min:0|max:'.$submission->assignment->max_score,
            'feedback' => 'nullable|string|max:20000',
            'status' => 'required|in:graded,returned',
        ]);
        DB::transaction(fn () => $submission->update([...$data, 'graded_by' => $request->user()->id, 'graded_at' => now()]));
        $submission->load('user', 'assignment.lesson');
        if ($submission->user && $submission->assignment->lesson) {
            app(NotificationService::class)->send($submission->user, 'learning', [
                'type' => 'assignment_graded',
                'title' => 'Your work was reviewed',
                'body' => "“{$submission->assignment->title}”: {$data['score']}/{$submission->assignment->max_score}"
                    .($data['status'] === 'returned' ? '. Please revise and resubmit.' : '.'),
                'action_url' => route('lessons.show', ['course' => $submission->assignment->course->slug, 'lesson' => $submission->assignment->lesson_id]),
                'event_key' => 'assignment-graded:'.$submission->id.':'.now()->timestamp,
            ]);
        }

        return back()->with('success', 'Grade saved.');
    }
}
