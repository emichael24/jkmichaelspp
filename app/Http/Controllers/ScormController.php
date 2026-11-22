<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\ScormProgress;
use App\Models\User;
use App\Services\CourseCompletionService;
use App\Services\LearningAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScormController extends Controller
{
    /**
     * Handle SCORM run-time tracking cmi.* commits from the iframe player.
     */
    public function commit(Request $request, int $lessonId)
    {
        $lesson = Lesson::with('module.course')->findOrFail($lessonId);
        app(LearningAccessService::class)->assertLesson($lesson, $request->user());
        abort_unless($lesson->scorm_path, 422, 'This lesson does not contain a SCORM package.');
        $validated = $request->validate([
            'cmi_core_lesson_status' => 'required|in:passed,completed,incomplete,failed,browsed,not attempted',
            'cmi_core_score_raw' => 'nullable|integer|min:0|max:100',
            'cmi_core_lesson_location' => 'nullable|string|max:4096',
            'suspend_data' => 'nullable|array|max:1',
            'suspend_data.data' => 'nullable|string|max:64000',
        ]);

        [$progress, $lessonProgress] = DB::transaction(function () use ($request, $lesson, $validated) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $progress = ScormProgress::firstOrNew(['user_id' => $request->user()->id, 'lesson_id' => $lesson->id]);
            $values = $validated;
            // Older player commits must not erase earned completion or a higher score.
            if ($progress->cmi_core_lesson_status === 'passed' ||
                ($progress->cmi_core_lesson_status === 'completed' && $values['cmi_core_lesson_status'] !== 'passed')) {
                $values['cmi_core_lesson_status'] = $progress->cmi_core_lesson_status;
            }
            if ($progress->cmi_core_score_raw !== null) {
                $values['cmi_core_score_raw'] = max($progress->cmi_core_score_raw, $values['cmi_core_score_raw'] ?? 0);
            }
            $progress->fill($values)->save();
            $lessonProgress = app(CourseCompletionService::class)->progress(
                $request->user(), $lesson, ['progress_pct' => 50], in_array($progress->cmi_core_lesson_status, ['completed', 'passed'], true)
            );

            return [$progress, $lessonProgress];
        }, 3);

        return response()->json([
            'status' => 'success',
            'message' => 'SCORM progress saved successfully.',
            'data' => $progress,
            'lesson_progress' => $lessonProgress,
            'completed' => $lessonProgress->status === 'completed',
        ]);
    }
}
