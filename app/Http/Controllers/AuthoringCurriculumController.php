<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Services\CurriculumRevisionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuthoringCurriculumController extends Controller
{
    public function reorder(Request $request, Course $course)
    {
        abort_unless($request->user()->hasRole('platform_admin') || $course->author_id === $request->user()->id, 403);
        $data = $request->validate(['module_id' => 'nullable|integer', 'order' => 'required|array', 'order.*' => 'required|integer|distinct']);
        DB::transaction(function () use ($course, $data) {
            $course = Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
            app(CurriculumRevisionService::class)->assertEditable($course);
            $items = isset($data['module_id']) ? $course->modules()->findOrFail($data['module_id'])->lessons()->get() : $course->modules()->get();
            $expected = $items->modelKeys();
            if (count($expected) !== count($data['order']) || array_diff($expected, $data['order']) || array_diff($data['order'], $expected)) {
                throw ValidationException::withMessages(['order' => 'The curriculum changed. Refresh the page and try again.']);
            }
            foreach ($data['order'] as $position => $id) {
                $items->find($id)->update(['sort_order' => $position + 1]);
            }
        });

        return back()->with('success', 'Curriculum order saved.');
    }

    /**
     * Course-wide drip schedule: sequential locking plus each lesson's release rule,
     * saved in one request from the course builder's Drip tab.
     */
    public function drip(Request $request, Course $course)
    {
        abort_unless($request->user()->hasRole('platform_admin') || $course->author_id === $request->user()->id, 403);
        $data = $request->validate([
            'sequential_lessons' => 'required|boolean',
            'lessons' => 'present|array|max:2000',
            'lessons.*.id' => 'required|integer|distinct',
            'lessons.*.unlock_type' => 'required|in:immediate,date,days_after_enrollment',
            'lessons.*.unlock_at' => 'nullable|required_if:lessons.*.unlock_type,date|date',
            'lessons.*.unlock_days_after_enrollment' => 'nullable|required_if:lessons.*.unlock_type,days_after_enrollment|integer|min:1|max:365',
        ], [
            'lessons.*.unlock_at.required_if' => 'Choose a release date for every lesson scheduled by date.',
            'lessons.*.unlock_days_after_enrollment.required_if' => 'Enter the number of days for every lesson released after enrollment.',
        ]);
        DB::transaction(function () use ($course, $data) {
            $course = Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
            app(CurriculumRevisionService::class)->assertEditable($course);
            $lessons = $course->lessons()->get()->keyBy('id');
            foreach ($data['lessons'] as $row) {
                $lesson = $lessons->get($row['id']);
                if (! $lesson) {
                    throw ValidationException::withMessages(['lessons' => 'The curriculum changed. Refresh the page and try again.']);
                }
                $lesson->update([
                    'unlock_type' => $row['unlock_type'],
                    'unlock_at' => $row['unlock_type'] === 'date' ? $row['unlock_at'] : null,
                    'unlock_days_after_enrollment' => $row['unlock_type'] === 'days_after_enrollment' ? (int) $row['unlock_days_after_enrollment'] : null,
                ]);
            }
            $course->update(['sequential_lessons' => $data['sequential_lessons']]);
        });

        return back()->with('success', 'Drip schedule saved.');
    }
}
