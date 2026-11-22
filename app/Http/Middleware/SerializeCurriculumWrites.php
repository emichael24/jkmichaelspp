<?php

namespace App\Http\Middleware;

use App\Models\Course;
use App\Services\CurriculumRevisionService;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SerializeCurriculumWrites
{
    public function handle(Request $request, Closure $next)
    {
        $controller = $request->route()?->getActionName() ?? '';
        $authoring = preg_match('~Controllers\\\\(?:Admin|Facilitator)\\\\(?:CourseController|CourseMediaController|QuizController|MockExamController|QuestionController|QuestionImportController)@~', $controller);
        if (! $authoring || $request->isMethodSafe() || ! $request->user()) {
            return $next($request);
        }
        $action = $request->route()->getActionMethod();
        $model = collect(['course', 'module', 'lesson', 'quiz', 'mockExam', 'question', 'resource'])->map(fn ($key) => $request->route($key))->first(fn ($value) => $value instanceof Model);
        $courseId = $model instanceof Course ? $model->id : ($model?->course_id ?? $model?->module?->course_id ?? $request->input('course_id'));
        if (! $courseId) {
            return $next($request);
        }

        return DB::transaction(function () use ($request, $next, $courseId, $action, $model) {
            $course = Course::whereKey($courseId)->lockForUpdate()->firstOrFail();
            $lifecycle = $model instanceof Course && in_array($action, ['publish', 'unpublish', 'submitForReview'], true);
            if (! $lifecycle) {
                app(CurriculumRevisionService::class)->assertEditable($course);
            }

            return $next($request);
        });
    }
}
