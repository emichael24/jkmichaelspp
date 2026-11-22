<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\ContentApproval;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonVideoQuiz;
use App\Models\MockExam;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CurriculumRevisionService
{
    public function assertEditable(Course $course): void
    {
        abort_if($course->is_published || $course->published_at || $course->approval_status === 'approved', 403, 'Create a draft revision to change approved or published content.');
        abort_if($course->approval_status === 'pending_review', 403, 'Recall the review before editing this draft.');
    }

    public function snapshot(Model $content): array
    {
        $attributes = fn (Model $model) => collect($model->attributesToArray())->except([
            'created_at', 'updated_at', 'deleted_at', 'approval_status', 'approved_by', 'approved_at',
            'submitted_for_review_at', 'rejection_reason', 'published_at', 'superseded_at',
            'total_enrollments', 'avg_rating', 'exposure_count', 'correct_rate', 'avg_time_seconds', 'discrimination_index', 'download_count',
        ])->all();
        $data = $attributes($content);
        if ($content instanceof Course) {
            $data['modules'] = $content->modules()->orderBy('id')->get()->map(function ($module) use ($attributes) {
                $row = $attributes($module);
                $row['lessons'] = $module->lessons()->orderBy('id')->get()->map(function ($lesson) use ($attributes) {
                    return [...$attributes($lesson), 'video_quizzes' => $lesson->videoQuizzes()->orderBy('id')->get()->map($attributes)->all()];
                })->all();

                return $row;
            })->all();
            $data['resources'] = $content->resources()->orderBy('id')->get()->map($attributes)->all();
            $data['prerequisite_ids'] = DB::table('course_prerequisites')->where('course_id', $content->id)->orderBy('prerequisite_id')->pluck('prerequisite_id')->all();
            $data['questions'] = Question::where('course_id', $content->id)->orderBy('id')->get()->map($attributes)->all();
            $data['quizzes'] = Quiz::where('course_id', $content->id)->orderBy('id')->get()->map(fn ($quiz) => [
                ...$attributes($quiz), 'questions' => $quiz->questions()->get()->map(fn ($q) => ['id' => $q->id, 'sort_order' => $q->pivot->sort_order])->all(),
            ])->all();
            $data['mock_exams'] = MockExam::where('course_id', $content->id)->orderBy('id')->get()->map($attributes)->all();
            $data['lesson_assignments'] = Assignment::where('course_id', $content->id)->whereNotNull('lesson_id')->orderBy('id')->get()->map($attributes)->all();
        }
        if ($content instanceof Lesson) {
            $data['video_quizzes'] = $content->videoQuizzes()->orderBy('id')->get()->map($attributes)->all();
        }

        return $data;
    }

    public function hash(Model $content): string
    {
        return hash('sha256', json_encode($this->snapshot($content), JSON_THROW_ON_ERROR));
    }

    public function recall(Course $course, User $actor): void
    {
        abort_unless($actor->hasRole('platform_admin') || $course->author_id === $actor->id, 403);
        DB::transaction(function () use ($course, $actor) {
            $course = Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
            abort_unless($course->approval_status === 'pending_review', 409, 'Only a pending review can be recalled.');
            ContentApproval::forContent(Course::class, $course->id)->pending()->get()->each(fn ($approval) => $approval->update([
                'status' => 'changes_requested', 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_notes' => 'Recalled by the author or administrator.',
            ]));
            $course->update(['approval_status' => 'draft', 'submitted_for_review_at' => null]);
        });
    }

    public function draft(Course $source, User $actor, ?array $snapshot = null): Course
    {
        abort_unless($actor->hasRole('platform_admin') || $source->author_id === $actor->id, 403);

        return DB::transaction(function () use ($source, $snapshot) {
            $source = Course::whereKey($source->id)->lockForUpdate()->firstOrFail();
            abort_unless($source->is_published || $source->published_at || $source->approval_status === 'approved', 409, 'This course is already a draft.');
            $existing = Course::where('revision_of_id', $source->id)->whereNull('published_at')->first();
            if ($existing) {
                abort_if($snapshot !== null, 409, 'A draft revision already exists. Review that draft before restoring another version.');

                return $existing;
            }
            $data = $snapshot ?? $this->snapshot($source);
            $copy = new Course(collect($data)->except(['id', 'uuid', 'slug', 'modules', 'resources', 'questions', 'quizzes', 'mock_exams', 'prerequisite_ids', 'lesson_assignments'])->all());
            $copy->fill(['uuid' => (string) Str::uuid(), 'slug' => $source->slug.'-revision-'.Str::lower(Str::random(8)),
                'revision_of_id' => $source->id, 'version' => $source->version + 1, 'is_published' => false, 'published_at' => null,
                'approval_status' => 'draft', 'approved_by' => null, 'approved_at' => null, 'submitted_for_review_at' => null, 'rejection_reason' => null,
                'total_enrollments' => 0, 'avg_rating' => 0, 'superseded_at' => null]);
            $copy->save();
            app(CoursePrerequisiteService::class)->replace($copy, $data['prerequisite_ids'] ?? DB::table('course_prerequisites')->where('course_id', $source->id)->pluck('prerequisite_id')->all());
            $moduleIds = [];
            $lessonIds = [];
            $questionIds = [];
            $videoQuizzes = [];
            foreach ($data['modules'] ?? [] as $module) {
                $newModule = $copy->modules()->create(collect($module)->except(['id', 'course_id', 'lessons'])->all());
                $moduleIds[$module['id']] = $newModule->id;
                foreach ($module['lessons'] ?? [] as $lesson) {
                    $newLesson = $newModule->lessons()->create(collect($lesson)->except(['id', 'module_id', 'video_quizzes'])->all());
                    $lessonIds[$lesson['id']] = $newLesson->id;
                    $videoQuizzes[$newLesson->id] = $lesson['video_quizzes'] ?? [];
                }
            }
            foreach ($data['questions'] ?? [] as $question) {
                $newQuestion = Question::create([...collect($question)->except(['id', 'uuid', 'course_id', 'linked_lesson_id'])->all(),
                    'course_id' => $copy->id, 'linked_lesson_id' => $lessonIds[$question['linked_lesson_id'] ?? 0] ?? null]);
                $questionIds[$question['id']] = $newQuestion->id;
            }
            foreach ($videoQuizzes as $lessonId => $items) {
                foreach ($items as $item) {
                    abort_unless(isset($questionIds[$item['question_id']]), 422, 'A video check references a question outside this curriculum.');
                    LessonVideoQuiz::create(['lesson_id' => $lessonId, 'question_id' => $questionIds[$item['question_id']],
                        'timestamp_seconds' => $item['timestamp_seconds'], 'require_passing' => $item['require_passing']]);
                }
            }
            foreach ($data['quizzes'] ?? [] as $quiz) {
                $newQuiz = Quiz::create([...collect($quiz)->except(['id', 'course_id', 'module_id', 'lesson_id', 'questions'])->all(),
                    'course_id' => $copy->id, 'module_id' => $moduleIds[$quiz['module_id'] ?? 0] ?? null, 'lesson_id' => $lessonIds[$quiz['lesson_id'] ?? 0] ?? null]);
                foreach ($quiz['questions'] ?? [] as $question) {
                    abort_unless(isset($questionIds[$question['id']]), 422, 'A quiz references a question outside this curriculum.');
                    $newQuiz->questions()->attach($questionIds[$question['id']], ['sort_order' => $question['sort_order']]);
                }
            }
            foreach ($data['lesson_assignments'] ?? [] as $assignment) {
                if (isset($lessonIds[$assignment['lesson_id']])) {
                    Assignment::create([...collect($assignment)->except(['id', 'course_id', 'lesson_id', 'cohort_id'])->all(),
                        'course_id' => $copy->id, 'lesson_id' => $lessonIds[$assignment['lesson_id']]]);
                }
            }
            foreach ($data['mock_exams'] ?? [] as $exam) {
                MockExam::create([...collect($exam)->except(['id', 'course_id'])->all(), 'course_id' => $copy->id]);
            }
            foreach ($data['resources'] ?? [] as $resource) {
                $copy->resources()->create([...collect($resource)->except(['id', 'course_id'])->all(), 'download_count' => 0]);
            }

            return $copy;
        });
    }
}
