<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Validation\ValidationException;

class CourseReadinessService
{
    /** Read current persisted content; callers must never trust a client readiness flag. */
    public function issues(Course $course): array
    {
        $issues = [];
        if (! trim(strip_tags($course->description ?? ''))) {
            $issues[] = 'Add a course description.';
        }
        if (empty(array_filter($course->learning_outcomes ?? [], fn ($outcome) => is_string($outcome) && trim($outcome) !== ''))) {
            $issues[] = 'Add at least one learning outcome.';
        }
        $modules = $course->modules()->where('is_published', true)->with('lessons.quiz', 'lessons.assignment')->get();
        if ($modules->isEmpty()) {
            $issues[] = 'Add at least one visible module.';
        }
        foreach ($modules as $module) {
            $lessons = $module->lessons->where('is_published', true);
            if ($lessons->isEmpty()) {
                $issues[] = "Add a visible lesson to {$module->title}.";
            }
            foreach ($lessons as $lesson) {
                if ($lesson->isActivity()) {
                    foreach ($this->activityIssues($lesson) as $issue) {
                        $issues[] = $issue;
                    }
                    if ($lesson->unlock_type === 'date' && ! $lesson->unlock_at) {
                        $issues[] = "Set an unlock date for {$lesson->title}.";
                    }

                    continue;
                }
                $field = match ($lesson->content_type) {
                    'text' => 'text_content', 'video' => 'video_url', 'audio' => 'audio_url',
                    'pdf' => 'pdf_url', 'slides' => 'slides_url', default => null,
                };
                $hasContent = $lesson->scorm_path || ($field
                    ? trim(strip_tags($lesson->$field ?? '')) !== ''
                    : collect(['text_content', 'video_url', 'audio_url', 'pdf_url', 'slides_url'])->contains(fn ($key) => trim(strip_tags($lesson->$key ?? '')) !== ''));
                if (! $hasContent) {
                    $issues[] = "Add {$lesson->content_type} content to {$lesson->title}.";
                }
                if ($lesson->unlock_type === 'date' && ! $lesson->unlock_at) {
                    $issues[] = "Set an unlock date for {$lesson->title}.";
                }
                if ($lesson->unlock_type === 'days_after_enrollment' && ! $lesson->unlock_days_after_enrollment) {
                    $issues[] = "Set the unlock delay for {$lesson->title}.";
                }
            }
        }

        return $issues;
    }

    private function activityIssues(Lesson $lesson): array
    {
        return match ($lesson->content_type) {
            'quiz' => ($lesson->quiz?->questions()->count() ?? 0) > 0 ? [] : ["Add at least one question to the quiz “{$lesson->title}”."],
            'assignment' => array_values(array_filter([
                trim(strip_tags($lesson->text_content ?? '')) === '' ? "Add instructions to the assignment “{$lesson->title}”." : null,
                $lesson->assignment ? null : "Save the assignment settings for “{$lesson->title}”.",
            ])),
            'case_study' => array_values(array_filter([
                trim(strip_tags($lesson->text_content ?? '')) === '' && ! $lesson->pdf_url ? "Add the scenario to the case study “{$lesson->title}”." : null,
                empty($lesson->assignment?->prompts) ? "Add at least one question to the case study “{$lesson->title}”." : null,
            ])),
            default => [],
        };
    }

    public function assertReady(Course $course): void
    {
        $issues = $this->issues($course);
        if ($issues) {
            throw ValidationException::withMessages(['readiness' => $issues]);
        }
    }
}
