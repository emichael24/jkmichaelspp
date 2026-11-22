<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Resource;
use App\Models\User;

class LearningAccessService
{
    public function assertCourse(Course $course, User $user): void
    {
        $this->assertActiveAccount($user);
        abort_unless($course->is_published, 404);
        abort_unless($course->enrollmentFor($user)?->allowsAccess(), 403, 'An active enrollment is required.');
        app(CoursePrerequisiteService::class)->assertSatisfied($course, $user);
    }

    public function assertLesson(Lesson $lesson, User $user, bool $allowPreview = false): void
    {
        $this->assertActiveAccount($user);
        $lesson->loadMissing('module.course');
        $course = $lesson->module?->course;
        abort_unless($course && $course->is_published && $lesson->module->is_published && $lesson->is_published, 404);
        $enrollment = $course->enrollmentFor($user);
        abort_unless($enrollment?->allowsAccess() || ($allowPreview && $lesson->is_free_preview), 403, 'An active enrollment is required.');
        app(CoursePrerequisiteService::class)->assertSatisfied($course, $user);
        abort_unless($lesson->isUnlockedFor($enrollment), 403, 'This lesson is not available yet.');
        if ($enrollment?->allowsAccess() && ($blocker = $this->sequentialBlocker($lesson, $user))) {
            abort(403, 'Complete “'.$blocker['title'].'” before starting this lesson.');
        }
    }

    /**
     * Published lessons in learner order (module order, then lesson order).
     *
     * @return array<int, array{id:int, title:string}>
     */
    public function orderedLessons(Course $course): array
    {
        return Lesson::query()->join('modules', 'modules.id', '=', 'lessons.module_id')
            ->where('modules.course_id', $course->id)->where('modules.is_published', true)->where('lessons.is_published', true)
            ->orderBy('modules.sort_order')->orderBy('modules.id')->orderBy('lessons.sort_order')->orderBy('lessons.id')
            ->get(['lessons.id', 'lessons.title'])->map(fn ($lesson) => ['id' => $lesson->id, 'title' => $lesson->title])->all();
    }

    /**
     * IDs of lessons a learner cannot open yet because the course requires lessons in order.
     * A lesson unlocks once every earlier lesson is complete.
     *
     * @return array<int, string> lesson id => title of the lesson to finish first
     */
    public function sequentialLocks(Course $course, User $user, ?array $ordered = null): array
    {
        if (! $course->sequential_lessons) {
            return [];
        }
        $ordered ??= $this->orderedLessons($course);
        $completed = LessonProgress::where('user_id', $user->id)->whereIn('lesson_id', array_column($ordered, 'id'))
            ->where('status', 'completed')->pluck('lesson_id')->flip();
        $locks = [];
        $blocker = null;
        foreach ($ordered as $lesson) {
            if ($blocker !== null) {
                $locks[$lesson['id']] = $blocker;
            } elseif (! isset($completed[$lesson['id']])) {
                $blocker = $lesson['title'];
            }
        }

        return $locks;
    }

    /** @return array{id:int, title:string}|null */
    public function sequentialBlocker(Lesson $lesson, User $user): ?array
    {
        $course = $lesson->module?->course;
        if (! $course?->sequential_lessons) {
            return null;
        }
        $ordered = $this->orderedLessons($course);
        $completed = LessonProgress::where('user_id', $user->id)->whereIn('lesson_id', array_column($ordered, 'id'))
            ->where('status', 'completed')->pluck('lesson_id')->flip();
        foreach ($ordered as $item) {
            if ($item['id'] === $lesson->id) {
                return null;
            }
            if (! isset($completed[$item['id']])) {
                return $item;
            }
        }

        return null;
    }

    public function assertResource(Resource $resource, User $user): void
    {
        abort_unless($resource->is_published && $resource->course, 404);
        $this->assertCourse($resource->course, $user);
    }

    public function assertActiveAccount(User $user): void
    {
        // Signed SCORM URLs and queued work do not necessarily pass through session middleware.
        $current = User::with('organization')->find($user->id);
        abort_unless($current?->is_active, 403, 'Your account is inactive.');
        abort_if($current->organization_id && (! $current->organization || ! $current->organization->is_active) && ! $current->hasRole('platform_admin'), 403, 'Your organization is inactive.');
    }
}
