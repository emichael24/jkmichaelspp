<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Quiz, assignment and case-study lessons. The lesson row carries the title,
 * instructions/scenario (text_content) and release rules; the linked Quiz or
 * Assignment carries grading settings so existing quiz players, gradebooks and
 * certificate evidence keep working unchanged.
 */
class LessonActivityService
{
    public const QUESTION_TYPES = ['multiple_choice', 'multiple_select', 'true_false'];

    public const SUBMISSION_MIMES = 'pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,png,jpg,jpeg,zip';

    public function rules(): array
    {
        return [
            'activity' => 'nullable|array',
            'activity.passing_score_pct' => 'nullable|integer|min:1|max:100',
            'activity.time_limit_minutes' => 'nullable|integer|min:1|max:600',
            'activity.max_attempts' => 'nullable|integer|min:1|max:100',
            'activity.is_randomized' => 'nullable|boolean',
            'activity.questions' => 'nullable|array|max:200',
            'activity.questions.*.id' => 'nullable|integer',
            'activity.questions.*.type' => 'required|in:'.implode(',', self::QUESTION_TYPES),
            'activity.questions.*.stem' => 'required|string|max:10000',
            'activity.questions.*.options' => 'required|array|min:2|max:10',
            'activity.questions.*.options.*.id' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]{1,64}$/'],
            'activity.questions.*.options.*.text' => 'required|string|max:2000',
            'activity.questions.*.correct_answer_ids' => 'required|array|min:1|max:10',
            'activity.questions.*.correct_answer_ids.*' => 'string|max:64',
            'activity.questions.*.explanation' => 'nullable|string|max:5000',
            'activity.max_score' => 'nullable|integer|min:1|max:1000',
            'activity.passing_score' => 'nullable|integer|min:0|max:1000',
            'activity.allow_file_upload' => 'nullable|boolean',
            'activity.max_resubmissions' => 'nullable|integer|min:0|max:20',
            'activity.prompts' => 'nullable|array|max:30',
            'activity.prompts.*' => 'nullable|string|max:2000',
        ];
    }

    /** Cross-field checks the rule array cannot express. */
    public function assertValid(string $contentType, ?array $activity, Course $course): void
    {
        $activity ??= [];
        if ($contentType === 'quiz') {
            foreach (array_values($activity['questions'] ?? []) as $index => $question) {
                try {
                    app(QuestionDefinitionValidator::class)->validate([
                        'type' => $question['type'], 'options' => $question['options'],
                        'correct_answer_ids' => array_values($question['correct_answer_ids']), 'course_id' => $course->id,
                    ]);
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages(["activity.questions.$index" => 'Question '.($index + 1).': '.collect($exception->errors())->flatten()->first()]);
                }
            }
        }
        if (in_array($contentType, ['assignment', 'case_study'], true)) {
            $max = (int) ($activity['max_score'] ?? 100);
            if (isset($activity['passing_score']) && $activity['passing_score'] !== null && (int) $activity['passing_score'] > $max) {
                throw ValidationException::withMessages(['activity.passing_score' => 'The pass mark cannot exceed the maximum score.']);
            }
        }
    }

    public function sync(Lesson $lesson, ?array $activity, User $actor): void
    {
        $lesson->loadMissing('module.course');
        DB::transaction(function () use ($lesson, $activity, $actor) {
            if ($lesson->content_type === 'quiz') {
                $this->syncQuiz($lesson, $activity ?? []);
            } else {
                $this->retireQuiz($lesson);
            }
            if (in_array($lesson->content_type, ['assignment', 'case_study'], true)) {
                $this->syncAssignment($lesson, $activity ?? [], $actor);
            } else {
                $this->retireAssignment($lesson);
            }
        });
    }

    private function syncQuiz(Lesson $lesson, array $activity): void
    {
        $course = $lesson->module->course;
        $quiz = $lesson->quiz ?? new Quiz(['lesson_id' => $lesson->id, 'type' => 'lesson_check']);
        $quiz->fill([
            'course_id' => $course->id,
            'module_id' => $lesson->module_id,
            'title' => $lesson->title,
            'passing_score_pct' => (int) ($activity['passing_score_pct'] ?? $quiz->passing_score_pct ?? 70),
            'time_limit_minutes' => $activity['time_limit_minutes'] ?? null,
            'max_attempts' => $activity['max_attempts'] ?? null,
            'is_randomized' => (bool) ($activity['is_randomized'] ?? false),
            'question_count' => $quiz->question_count ?? 0,
            'is_published' => false,
        ]);
        $quiz->save();

        $keep = [];
        foreach (array_values($activity['questions'] ?? []) as $position => $input) {
            $existing = ! empty($input['id']) ? Question::where('course_id', $course->id)->find($input['id']) : null;
            $attributes = [
                'type' => $input['type'],
                'stem' => $input['stem'],
                'options' => array_map(fn ($option) => ['id' => $option['id'], 'text' => $option['text']], array_values($input['options'])),
                'correct_answer_ids' => array_values($input['correct_answer_ids']),
                'explanation_correct' => $input['explanation'] ?? '',
            ];
            if ($existing && (int) $existing->linked_lesson_id === $lesson->id) {
                $existing->update($attributes);
            } elseif (! $existing) {
                // Questions written in the lesson editor join the course question bank,
                // linked to this lesson for remediation and analytics.
                $existing = Question::create([...$attributes, 'course_id' => $course->id, 'domain' => Str::limit($lesson->module->title, 250, ''),
                    'subtopic' => Str::limit($lesson->title, 250, ''), 'difficulty' => 'medium', 'status' => 'published', 'linked_lesson_id' => $lesson->id]);
            }
            // Bank questions picked from another lesson are attached unchanged.
            $keep[$existing->id] = ['sort_order' => $position + 1];
        }

        $removed = $quiz->questions()->pluck('questions.id')->diff(array_keys($keep));
        $quiz->questions()->sync($keep);
        Question::whereIn('id', $removed)->where('linked_lesson_id', $lesson->id)
            ->whereDoesntHave('quizzes')->get()->each->delete();

        $count = count($keep);
        $quiz->update(['question_count' => $count, 'is_published' => $lesson->is_published && $count > 0]);
    }

    private function retireQuiz(Lesson $lesson): void
    {
        $quiz = $lesson->quiz;
        if (! $quiz) {
            return;
        }
        // An abandoned lesson quiz must not keep counting toward course completion.
        $quiz->attempts()->exists() ? $quiz->update(['is_published' => false]) : $quiz->delete();
    }

    private function syncAssignment(Lesson $lesson, array $activity, User $actor): void
    {
        $assignment = $lesson->assignment ?? new Assignment(['lesson_id' => $lesson->id, 'created_by' => $actor->id]);
        $assignment->fill([
            'course_id' => $lesson->module->course_id,
            'title' => $lesson->title,
            'description' => (string) ($lesson->summary ?: Str::limit(trim(strip_tags($lesson->text_content ?? '')), 500)),
            'max_score' => (int) ($activity['max_score'] ?? $assignment->max_score ?? 100),
            'passing_score' => $activity['passing_score'] ?? null,
            'allow_file_upload' => (bool) ($activity['allow_file_upload'] ?? true),
            'max_resubmissions' => (int) ($activity['max_resubmissions'] ?? $assignment->max_resubmissions ?? 1),
            'prompts' => $lesson->content_type === 'case_study'
                ? array_values(array_filter(array_map(fn ($prompt) => trim((string) $prompt), $activity['prompts'] ?? []), fn ($prompt) => $prompt !== ''))
                : null,
            'is_published' => $lesson->is_published,
        ]);
        $assignment->save();
    }

    private function retireAssignment(Lesson $lesson): void
    {
        $assignment = $lesson->assignment;
        if (! $assignment) {
            return;
        }
        $assignment->submissions()->exists() ? $assignment->update(['is_published' => false]) : $assignment->delete();
    }

    /** Settings the lesson editor needs to round-trip the activity. */
    public function editorPayload(Lesson $lesson): ?array
    {
        if ($lesson->content_type === 'quiz') {
            $quiz = $lesson->quiz()->with('questions')->first();

            return [
                'passing_score_pct' => $quiz?->passing_score_pct ?? 70,
                'time_limit_minutes' => $quiz?->time_limit_minutes,
                'max_attempts' => $quiz?->max_attempts,
                'is_randomized' => (bool) ($quiz?->is_randomized ?? false),
                'questions' => ($quiz?->questions ?? collect())->map(fn (Question $question) => $this->questionForEditor($question, $lesson))->values()->all(),
            ];
        }
        if (in_array($lesson->content_type, ['assignment', 'case_study'], true)) {
            $assignment = $lesson->assignment;

            return [
                'max_score' => $assignment?->max_score ?? 100,
                'passing_score' => $assignment?->passing_score,
                'allow_file_upload' => $assignment?->allow_file_upload ?? true,
                'max_resubmissions' => $assignment?->max_resubmissions ?? 1,
                'prompts' => $assignment?->prompts ?? [],
                'submission_count' => $assignment ? $assignment->submissions()->count() : 0,
                'pending_count' => $assignment ? $assignment->submissions()->whereIn('status', ['submitted', 'resubmitted', 'in_review'])->count() : 0,
            ];
        }

        return null;
    }

    /** Course question-bank items an author may reuse in a lesson quiz. */
    public function questionBank(Course $course): array
    {
        return Question::where('course_id', $course->id)->published()->whereIn('type', self::QUESTION_TYPES)
            ->latest('id')->limit(300)->get()
            ->map(fn (Question $question) => $this->questionForEditor($question, null))->all();
    }

    private function questionForEditor(Question $question, ?Lesson $lesson): array
    {
        return [
            'id' => $question->id,
            'type' => $question->type,
            'stem' => $question->stem,
            'options' => collect($question->options)->map(fn ($option) => ['id' => (string) $option['id'], 'text' => (string) $option['text']])->values()->all(),
            'correct_answer_ids' => array_values(array_map('strval', $question->correct_answer_ids ?? [])),
            'explanation' => $question->explanation_correct,
            'from_bank' => $lesson === null || (int) $question->linked_lesson_id !== $lesson->id,
            'domain' => $question->domain,
        ];
    }

    /** What the learner sees inside the lesson player. */
    public function learnerPayload(Lesson $lesson, User $user): ?array
    {
        if ($lesson->content_type === 'quiz') {
            $quiz = $lesson->quiz;
            if (! $quiz || ! $quiz->is_published) {
                return ['kind' => 'quiz', 'available' => false];
            }
            $attempts = QuizAttempt::where('quizzable_type', Quiz::class)->where('quizzable_id', $quiz->id)->where('user_id', $user->id);
            $completed = (clone $attempts)->where('status', 'completed');
            $best = (clone $completed)->max('score_pct');
            $last = (clone $completed)->latest('completed_at')->first(['id']);

            return [
                'kind' => 'quiz', 'available' => true, 'quiz_id' => $quiz->id,
                'question_count' => $quiz->question_count, 'passing_score_pct' => $quiz->passing_score_pct,
                'time_limit_minutes' => $quiz->time_limit_minutes, 'max_attempts' => $quiz->max_attempts,
                'attempts_used' => (clone $completed)->count(),
                'in_progress' => (clone $attempts)->where('status', 'in_progress')->exists(),
                'best_score' => $best !== null ? (float) $best : null,
                'passed' => $best !== null && (float) $best >= $quiz->passing_score_pct,
                'can_attempt' => $quiz->canAttempt($user),
                'last_attempt_id' => $last?->id,
            ];
        }
        if (in_array($lesson->content_type, ['assignment', 'case_study'], true)) {
            $assignment = $lesson->assignment;
            if (! $assignment || ! $assignment->is_published) {
                return ['kind' => $lesson->content_type, 'available' => false];
            }
            $submissions = $assignment->submissions()->where('user_id', $user->id)->orderByDesc('submission_number')->get();

            return [
                'kind' => $lesson->content_type, 'available' => true,
                'max_score' => $assignment->max_score, 'passing_score' => $assignment->passing_score,
                'allow_file_upload' => $assignment->allow_file_upload, 'prompts' => $assignment->prompts ?? [],
                'max_submissions' => $assignment->max_resubmissions + 1,
                'can_submit' => $this->canSubmit($assignment, $submissions->first(), $submissions->count()),
                'submissions' => $submissions->map(fn (AssignmentSubmission $submission) => $this->submissionPayload($submission))->all(),
            ];
        }

        return null;
    }

    private function canSubmit(Assignment $assignment, ?AssignmentSubmission $latest, int $count): bool
    {
        if (! $latest) {
            return true;
        }
        // A graded, passing submission is final; otherwise learners may resubmit within the allowance.
        $passed = $latest->status === 'graded' && $assignment->passing_score !== null && $latest->score >= $assignment->passing_score;

        return ! $passed && $count < $assignment->max_resubmissions + 1 && in_array($latest->status, ['graded', 'returned'], true);
    }

    public function submissionPayload(AssignmentSubmission $submission): array
    {
        return [
            'id' => $submission->id,
            'submission_number' => $submission->submission_number,
            'status' => $submission->status,
            'score' => $submission->score,
            'feedback' => $submission->feedback,
            'content' => $submission->content,
            'answers' => $submission->answers ?? [],
            'submitted_at' => $submission->submitted_at,
            'graded_at' => $submission->graded_at,
            'files' => collect($submission->file_urls ?? [])->values()->map(fn ($file, $index) => [
                'name' => $file['name'] ?? 'Attachment '.($index + 1),
                'size' => $file['size'] ?? null,
                'url' => url("/assignment-submissions/{$submission->id}/files/{$index}"),
            ])->all(),
        ];
    }

    /** @param UploadedFile[] $files */
    public function submit(Lesson $lesson, User $user, array $data, array $files): AssignmentSubmission
    {
        $assignment = $lesson->assignment;
        abort_unless($assignment && $assignment->is_published && in_array($lesson->content_type, ['assignment', 'case_study'], true), 404);
        abort_if($files && ! $assignment->allow_file_upload, 422, 'File uploads are not enabled for this activity.');
        $prompts = $assignment->prompts ?? [];
        $answers = null;
        if ($lesson->content_type === 'case_study' && $prompts) {
            $answers = [];
            foreach ($prompts as $index => $prompt) {
                $answer = trim((string) ($data['answers'][$index] ?? ''));
                if ($answer === '') {
                    throw ValidationException::withMessages(["answers.$index" => 'Answer question '.($index + 1).' before submitting.']);
                }
                $answers[] = ['prompt' => $prompt, 'answer' => $answer];
            }
        } elseif (trim(strip_tags((string) ($data['content'] ?? ''))) === '' && ! $files) {
            throw ValidationException::withMessages(['content' => 'Write a response or attach a file before submitting.']);
        }

        $stored = [];
        foreach ($files as $file) {
            $scanned = app(PrivateMediaService::class)->scan($file->getRealPath());
            $path = $file->storeAs('assignment-submissions/'.$assignment->id.'/'.$user->id,
                Str::uuid().'.'.strtolower($file->getClientOriginalExtension() ?: 'bin'), 'local');
            abort_unless($path, 503, 'Unable to store the attachment.');
            $stored[] = ['name' => Str::limit($file->getClientOriginalName(), 180, ''), 'path' => $path,
                'size' => $file->getSize(), 'mime' => $file->getMimeType(), 'scanned_at' => $scanned?->toIso8601String()];
        }

        try {
            $submission = DB::transaction(function () use ($assignment, $user, $data, $answers, $stored) {
                Assignment::whereKey($assignment->id)->lockForUpdate()->first();
                $previous = $assignment->submissions()->where('user_id', $user->id)->orderByDesc('submission_number')->get();
                abort_unless($this->canSubmit($assignment, $previous->first(), $previous->count()), 409,
                    $previous->first() && ! in_array($previous->first()->status, ['graded', 'returned'], true)
                        ? 'Your submission is awaiting review.' : 'No submissions remain for this activity.');

                return $assignment->submissions()->create([
                    'user_id' => $user->id,
                    'submission_number' => $previous->count() + 1,
                    'content' => isset($data['content']) ? Str::limit((string) $data['content'], 60000, '') : null,
                    'answers' => $answers,
                    'file_urls' => $stored ?: null,
                    'status' => $previous->isEmpty() ? 'submitted' : 'resubmitted',
                    'submitted_at' => now(),
                ]);
            });
        } catch (\Throwable $exception) {
            foreach ($stored as $file) {
                Storage::disk('local')->delete($file['path']);
            }
            throw $exception;
        }

        // Submitting completes the lesson so learners can continue; grading happens asynchronously.
        app(CourseCompletionService::class)->progress($user, $lesson, [], true);

        return $submission;
    }

    public function canGrade(User $user, Assignment $assignment): bool
    {
        $assignment->loadMissing('course');

        return $user->hasRole('platform_admin') || ($user->hasRole('facilitator') && $assignment->course?->author_id === $user->id);
    }
}
