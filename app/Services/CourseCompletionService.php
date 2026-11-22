<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CourseCompletionService
{
    public function progress(User $user, Lesson $lesson, array $values = [], bool $complete = false): LessonProgress
    {
        app(LearningAccessService::class)->assertLesson($lesson, $user);

        return DB::transaction(function () use ($user, $lesson, $values, $complete) {
            // All progress and certificate writes for a learner use the same lock order.
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $progress = LessonProgress::firstOrCreate(['user_id' => $user->id, 'lesson_id' => $lesson->id]);
            $progress->fill([
                'video_position_sec' => $values['video_position_sec'] ?? $progress->video_position_sec ?? 0,
                'time_spent_seconds' => max($progress->time_spent_seconds ?? 0, $values['time_spent_seconds'] ?? 0),
                'progress_pct' => max($progress->progress_pct ?? 0, $values['progress_pct'] ?? 0),
            ]);
            if ($complete) {
                $progress->status = 'completed';
                $progress->progress_pct = 100;
                $progress->completed_at ??= now();
            } elseif ($progress->status !== 'completed') {
                $progress->status = 'in_progress';
            }
            $progress->save();
            if ($complete) {
                app(GamificationService::class)->awardXp($user->id, 'lesson_complete', ['event_key' => 'lesson:'.$lesson->id]);
            }
            $this->reconcile($user, $lesson->module->course);

            return $progress;
        }, 3);
    }

    public function reconcile(User $user, Course $course): void
    {
        DB::transaction(function () use ($user, $course) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $enrollment = CourseEnrollment::where('course_id', $course->id)->where('user_id', $user->id)->lockForUpdate()->first();
            if (! $course->is_published || ! $enrollment?->allowsAccess()) {
                return;
            }
            $evidence = $this->evidence($user, $course);
            if (! count($evidence['lesson_ids'])) {
                return;
            }
            $eligible = $evidence['eligible'];
            // Preserve earned completion and its original date when the curriculum subsequently changes.
            if ($enrollment->status !== 'completed') {
                $enrollment->update([
                    'progress_pct' => $eligible ? 100 : min(99, (int) floor($evidence['completed_lessons'] / count($evidence['lesson_ids']) * 100)),
                    'status' => $eligible ? 'completed' : 'active',
                    'completed_at' => $eligible ? now() : null,
                ]);
            }
            if ($eligible) {
                app(CertificateService::class)->issue($user, $course, [
                    'lesson_ids' => $evidence['lesson_ids'], 'quiz_ids' => $evidence['quiz_ids'],
                    'completion_policy' => 'published_lessons_and_published_quizzes_v1',
                ]);
            }
        }, 3);
    }

    public function evidence(User $user, Course $course): array
    {
        $lessons = $course->lessons()->published()->whereHas('module', fn ($q) => $q->published())->pluck('lessons.id');
        $done = LessonProgress::where('user_id', $user->id)->whereIn('lesson_id', $lessons)->where('status', 'completed')->pluck('lesson_id');
        $quizzes = Quiz::published()->where('course_id', $course->id)
            ->where(fn ($q) => $q->whereNull('lesson_id')->orWhereIn('lesson_id', $lessons))
            ->where(fn ($q) => $q->whereNull('module_id')->orWhereHas('module', fn ($m) => $m->published()))->get();
        $missingQuizzes = $quizzes->reject(fn ($quiz) => $quiz->attempts()->where('user_id', $user->id)
            ->where('status', 'completed')->where('score_pct', '>=', $quiz->passing_score_pct ?? 70)->exists())->modelKeys();

        return ['eligible' => $course->is_published && $lessons->isNotEmpty() && $done->count() === $lessons->count() && ! $missingQuizzes,
            'lesson_ids' => $lessons->all(), 'quiz_ids' => $quizzes->modelKeys(), 'completed_lessons' => $done->count(),
            'missing_lesson_ids' => $lessons->diff($done)->values()->all(), 'missing_quiz_ids' => $missingQuizzes];
    }
}
