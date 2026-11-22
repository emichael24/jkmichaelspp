<?php

namespace App\Http\Controllers\Facilitator;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Cohort;
use App\Models\CourseEnrollment;
use App\Models\Meeting;
use App\Models\MeetingAttendee;
use App\Models\MockExam;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class GradebookController extends Controller
{
    public function index(Cohort $cohort)
    {
        $this->authorizeFacilitator($cohort);

        $cohort->load('course:id,title,slug');

        $members = $cohort->members()
            ->active()
            ->learners()
            ->whereHas('user', fn ($query) => $query->when($cohort->organization_id, fn ($query, $organizationId) => $query->where('organization_id', $organizationId)))
            ->with('user:id,first_name,last_name,email')
            ->get();

        $learnerIds = $members->pluck('user_id');

        $assignments = Assignment::where('cohort_id', $cohort->id)
            ->orderBy('due_date')
            ->orderBy('created_at')
            ->get();

        $submissions = AssignmentSubmission::whereIn('assignment_id', $assignments->pluck('id'))
            ->whereIn('user_id', $learnerIds)
            ->orderByDesc('submission_number')
            ->orderByDesc('submitted_at')
            ->get()
            ->groupBy(fn ($submission) => $submission->assignment_id.':'.$submission->user_id)
            ->map(fn (Collection $items) => $items->first());

        $enrollments = CourseEnrollment::where('course_id', $cohort->course_id)
            ->whereIn('user_id', $learnerIds)
            ->get()
            ->keyBy('user_id');

        $quizIds = Quiz::where('course_id', $cohort->course_id)->pluck('id');
        $mockIds = MockExam::where('course_id', $cohort->course_id)->pluck('id');

        $attempts = QuizAttempt::whereIn('user_id', $learnerIds)
            ->where('status', 'completed')
            ->where(function ($query) use ($quizIds, $mockIds) {
                $query->where(function ($inner) use ($quizIds) {
                    $inner->where('quizzable_type', Quiz::class)
                        ->whereIn('quizzable_id', $quizIds);
                })->orWhere(function ($inner) use ($mockIds) {
                    $inner->where('quizzable_type', MockExam::class)
                        ->whereIn('quizzable_id', $mockIds);
                });
            })
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn ($attempt) => $attempt->user_id.':'.$attempt->quizzable_type.':'.$attempt->quizzable_id)
            ->map(fn (Collection $items) => $items->first());

        $attemptsByUser = $attempts->groupBy('user_id');

        $meetings = Meeting::forCohort($cohort->id)
            ->where('status', 'completed')
            ->get(['id', 'scheduled_at']);

        $meetingIds = $meetings->pluck('id');
        $attendanceRows = $meetingIds->isNotEmpty()
            ? MeetingAttendee::whereIn('meeting_id', $meetingIds)
                ->whereIn('user_id', $learnerIds)
                ->get()
            : collect();
        $attendanceByUser = $attendanceRows->groupBy('user_id');
        $totalMeetings = $meetings->count();

        $assignmentColumns = $assignments->map(fn (Assignment $assignment) => [
            'id' => $assignment->id,
            'title' => $assignment->title,
            'max_score' => $assignment->max_score,
            'due_date' => $assignment->due_date?->toISOString(),
        ])->values();

        $learners = $members->map(function ($member) use ($assignments, $submissions, $enrollments) {
            $user = $member->user;
            $enrollment = $enrollments->get($member->user_id);
            $gradedScores = collect();
            $submittedCount = 0;
            $gradedCount = 0;
            $pendingCount = 0;
            $missingCount = 0;
            $lastSubmittedAt = null;

            $assignmentCells = $assignments->map(function (Assignment $assignment) use (
                $member,
                $submissions,
                &$gradedScores,
                &$submittedCount,
                &$gradedCount,
                &$pendingCount,
                &$missingCount,
                &$lastSubmittedAt
            ) {
                $submission = $submissions->get($assignment->id.':'.$member->user_id);
                $status = 'not_submitted';

                if ($submission) {
                    $submittedCount++;
                    $lastSubmittedAt = $this->latestDate($lastSubmittedAt, $submission->submitted_at?->toISOString());

                    if ($submission->status === 'graded') {
                        $status = 'graded';
                        $gradedCount++;

                        if ($submission->score !== null) {
                            $gradedScores->push(round(($submission->score / max($assignment->max_score ?: 100, 1)) * 100, 1));
                        }
                    } elseif ($submission->status === 'returned') {
                        $status = 'returned';
                    } else {
                        $status = 'pending';
                        $pendingCount++;
                    }
                } elseif ($assignment->due_date && $assignment->due_date->isPast()) {
                    $status = 'missing';
                    $missingCount++;
                }

                return [
                    'assignment_id' => $assignment->id,
                    'status' => $status,
                    'score' => $submission?->score,
                    'score_pct' => $submission && $submission->score !== null
                        ? round(($submission->score / max($assignment->max_score ?: 100, 1)) * 100, 1)
                        : null,
                    'submitted_at' => $submission?->submitted_at?->toISOString(),
                    'graded_at' => $submission?->graded_at?->toISOString(),
                ];
            })->values();

            return [
                'id' => $user->id,
                'name' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
                'email' => $user->email,
                'progress_pct' => $enrollment?->progress_pct ?? 0,
                'enrollment_status' => $enrollment?->status ?? 'not_enrolled',
                'average_score_pct' => $gradedScores->count() ? round($gradedScores->avg(), 1) : null,
                'submitted_count' => $submittedCount,
                'graded_count' => $gradedCount,
                'pending_count' => $pendingCount,
                'missing_count' => $missingCount,
                'last_submitted_at' => $lastSubmittedAt,
                'assignments' => $assignmentCells,
            ];
        })->values();

        $learners = $learners->map(function (array $learner) use ($attemptsByUser, $attendanceByUser, $totalMeetings) {
            $attemptsForUser = $attemptsByUser->get($learner['id'], collect());

            $quizAttempts = $attemptsForUser->filter(fn ($attempt) => $attempt->quizzable_type === Quiz::class);
            $mockAttempts = $attemptsForUser->filter(fn ($attempt) => $attempt->quizzable_type === MockExam::class);

            $quizAvg = $quizAttempts->count() ? round($quizAttempts->avg('score_pct'), 1) : null;
            $mockAvg = $mockAttempts->count() ? round($mockAttempts->avg('score_pct'), 1) : null;

            $attendanceRows = $attendanceByUser->get($learner['id'], collect());
            $attended = $attendanceRows->where('attended', true)->count();
            $attendanceRate = $totalMeetings > 0 ? round(($attended / $totalMeetings) * 100, 1) : null;

            $learner['quiz_avg_pct'] = $quizAvg;
            $learner['quiz_attempts'] = $quizAttempts->count();
            $learner['mock_avg_pct'] = $mockAvg;
            $learner['mock_attempts'] = $mockAttempts->count();
            $learner['attendance_rate'] = $attendanceRate;
            $learner['attendance_attended'] = $attended;
            $learner['attendance_total'] = $totalMeetings;

            return $learner;
        })->values();

        $summary = [
            'learners' => $learners->count(),
            'assignments' => $assignmentColumns->count(),
            'average_progress' => round($learners->avg('progress_pct') ?? 0),
            'average_grade' => round($learners->filter(fn ($learner) => $learner['average_score_pct'] !== null)->avg('average_score_pct') ?? 0, 1),
            'pending_reviews' => $learners->sum('pending_count'),
            'missing_submissions' => $learners->sum('missing_count'),
            'average_quiz_score' => round($learners->filter(fn ($learner) => $learner['quiz_avg_pct'] !== null)->avg('quiz_avg_pct') ?? 0, 1),
            'average_mock_score' => round($learners->filter(fn ($learner) => $learner['mock_avg_pct'] !== null)->avg('mock_avg_pct') ?? 0, 1),
            'attendance_rate' => round($learners->filter(fn ($learner) => $learner['attendance_rate'] !== null)->avg('attendance_rate') ?? 0, 1),
            'meetings' => $totalMeetings,
        ];

        return Inertia::render('Facilitator/Gradebook/Index', [
            'cohort' => $cohort,
            'summary' => $summary,
            'assignments' => $assignmentColumns,
            'learners' => $learners,
        ]);
    }

    private function latestDate(?string $current, ?string $candidate): ?string
    {
        if (! $candidate) {
            return $current;
        }

        if (! $current) {
            return $candidate;
        }

        return $candidate > $current ? $candidate : $current;
    }

    private function authorizeFacilitator(Cohort $cohort): void
    {
        abort_if($cohort->organization_id && ! $cohort->organization?->is_active && ! Auth::user()->hasRole('platform_admin'), 403, 'This organization is inactive.');
        abort_unless(
            $cohort->facilitator_id === Auth::id() || Auth::user()->hasRole('platform_admin'),
            403,
            'You do not have access to this cohort.'
        );
    }
}
