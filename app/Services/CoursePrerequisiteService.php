<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CoursePrerequisiteService
{
    public function replace(Course $course, array $ids): void
    {
        DB::transaction(function () use ($course, $ids) {
            DB::table('learning_rule_locks')->where('id', 1)->lockForUpdate()->firstOrFail();
            $ids = array_values(array_unique(array_map('intval', $ids)));
            if (in_array($course->id, $ids, true) || Course::whereIn('id', $ids)->published()->count() !== count($ids)) {
                throw ValidationException::withMessages(['prerequisite_ids' => 'Choose other published courses as prerequisites.']);
            }
            $edges = DB::table('course_prerequisites')->get()->groupBy('course_id')->map(fn ($rows) => $rows->pluck('prerequisite_id')->all())->all();
            $edges[$course->id] = $ids;
            $visiting = [];
            $visited = [];
            $walk = function (int $id) use (&$walk, &$visiting, &$visited, $edges) {
                if (isset($visiting[$id])) {
                    throw ValidationException::withMessages(['prerequisite_ids' => 'These prerequisites create a circular dependency. Remove the cycle before saving.']);
                }
                if (isset($visited[$id])) {
                    return;
                }
                $visiting[$id] = true;
                foreach ($edges[$id] ?? [] as $next) {
                    $walk((int) $next);
                }
                unset($visiting[$id]);
                $visited[$id] = true;
            };
            $walk($course->id);
            $before = DB::table('course_prerequisites')->where('course_id', $course->id)->pluck('prerequisite_id')->all();
            DB::table('course_prerequisites')->where('course_id', $course->id)->delete();
            foreach ($ids as $id) {
                DB::table('course_prerequisites')->insert(['course_id' => $course->id, 'prerequisite_id' => $id]);
            }
            AuditLog::log('prerequisite_policy', Course::class, $course->id, ['prerequisite_ids' => $before], ['prerequisite_ids' => $ids]);
        }, 3);
    }

    public function requirements(Course $course, User $user): array
    {
        $courses = Course::whereIn('id', DB::table('course_prerequisites')->where('course_id', $course->id)->select('prerequisite_id'))->get(['id', 'title', 'slug', 'is_published']);
        $completed = CourseEnrollment::where('user_id', $user->id)->whereIn('course_id', $courses->modelKeys())
            ->where('status', 'completed')->whereNotNull('completed_at')->pluck('course_id')->all();

        return $courses->map(fn ($required) => ['id' => $required->id, 'title' => $required->title,
            'url' => $required->is_published ? route('courses.show', $required->slug) : null,
            'completed' => in_array($required->id, $completed, true)])->all();
    }

    public function assertSatisfied(Course $course, User $user): void
    {
        abort_if(collect($this->requirements($course, $user))->contains('completed', false), 403, 'Complete the required prerequisite courses before continuing.');
    }
}
