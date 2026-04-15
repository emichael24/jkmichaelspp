<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\ScormProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScormController extends Controller
{
    /**
     * Handle SCORM run-time tracking cmi.* commits from the iframe player.
     */
    public function commit(Request $request, int $lessonId)
    {
        $validated = $request->validate([
            'cmi_core_lesson_status'   => 'required|string',
            'cmi_core_score_raw'       => 'nullable|integer',
            'cmi_core_lesson_location' => 'nullable|string',
            'suspend_data'             => 'nullable|array',
        ]);

        $progress = ScormProgress::updateOrCreate(
            ['user_id' => Auth::id(), 'lesson_id' => $lessonId],
            $validated
        );

        $lesson = Lesson::with('module.course')->findOrFail($lessonId);
        $lessonProgress = $this->syncLessonProgress($lesson, strtolower($validated['cmi_core_lesson_status']));
        $this->recalculateCourseProgress($lesson);

        return response()->json([
            'status'          => 'success',
            'message'         => 'SCORM progress saved successfully.',
            'data'            => $progress,
            'lesson_progress' => $lessonProgress,
            'completed'       => $lessonProgress->status === 'completed',
        ]);
    }

    private function syncLessonProgress(Lesson $lesson, string $lessonStatus): LessonProgress
    {
        $isCompleted = in_array($lessonStatus, ['completed', 'passed'], true);

        return LessonProgress::updateOrCreate(
            [
                'user_id'   => Auth::id(),
                'lesson_id' => $lesson->id,
            ],
            [
                'status'       => $isCompleted ? 'completed' : 'in_progress',
                'progress_pct' => $isCompleted ? 100 : 50,
                'completed_at' => $isCompleted ? now() : null,
            ]
        );
    }

    private function recalculateCourseProgress(Lesson $lesson): void
    {
        $course = $lesson->module->course;
        $user = Auth::user();

        $enrollment = $course->enrollments()
            ->where('user_id', $user->id)
            ->first();

        if (! $enrollment) {
            return;
        }

        $totalLessons = $course->lessons()->published()->count();
        if ($totalLessons === 0) {
            return;
        }

        $completedLessons = LessonProgress::where('user_id', $user->id)
            ->whereIn('lesson_id', $course->lessons()->published()->pluck('lessons.id'))
            ->where('status', 'completed')
            ->count();

        $progressPct = (int) round(($completedLessons / $totalLessons) * 100);

        $enrollment->update([
            'progress_pct' => $progressPct,
            'status'       => $progressPct >= 100 ? 'completed' : 'active',
            'completed_at' => $progressPct >= 100 ? now() : null,
        ]);
    }
}
