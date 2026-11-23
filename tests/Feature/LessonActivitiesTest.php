<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Category;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use App\Services\AssessmentAttemptService;
use App\Services\CourseReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LessonActivitiesTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function draftCourse(User $author): array
    {
        $course = Course::factory()->create(['author_id' => $author->id, 'approval_status' => 'draft', 'is_published' => false, 'published_at' => null]);
        $module = $course->modules()->create(['title' => 'Initiating', 'is_published' => true, 'sort_order' => 1]);

        return [$course, $module];
    }

    private function question(string $stem, string $correct = 'a'): array
    {
        return ['type' => 'multiple_choice', 'stem' => $stem, 'explanation' => 'Because.', 'correct_answer_ids' => [$correct],
            'options' => [['id' => 'a', 'text' => 'Charter'], ['id' => 'b', 'text' => 'Schedule'], ['id' => 'c', 'text' => 'Budget']]];
    }

    private function publishAndEnroll(Course $course): User
    {
        $course->update(['is_published' => true, 'published_at' => now(), 'approval_status' => 'approved']);
        $learner = User::factory()->create();
        $course->enrollments()->create(['user_id' => $learner->id, 'status' => 'active', 'enrolled_at' => now()]);

        return $learner;
    }

    public function test_quiz_lesson_saves_inline_questions_and_passing_completes_the_lesson(): void
    {
        $admin = $this->actor('platform_admin');
        [$course, $module] = $this->draftCourse($admin);
        $this->actingAs($admin)->get("/admin/modules/{$module->id}/lessons/create?type=quiz")
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Admin/Courses/Lessons/Create')->where('initialType', 'quiz'));

        $this->post("/admin/modules/{$module->id}/lessons", [
            'title' => 'Initiating check', 'content_type' => 'quiz', 'is_published' => true, 'stay_in_editor' => true,
            'activity' => ['passing_score_pct' => 50, 'max_attempts' => 3, 'questions' => [$this->question('What authorizes a project?'), $this->question('Which is a baseline?', 'b')]],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $lesson = Lesson::where('title', 'Initiating check')->firstOrFail();
        $quiz = Quiz::where('lesson_id', $lesson->id)->firstOrFail();
        $this->assertTrue($quiz->is_published);
        $this->assertSame(2, $quiz->question_count);
        $this->assertSame(50, $quiz->passing_score_pct);
        $this->assertSame(2, Question::where('linked_lesson_id', $lesson->id)->where('status', 'published')->count());

        // Editing removes one inline question and reuses a bank question.
        $bank = Question::create(['course_id' => $course->id, 'domain' => 'Bank', 'type' => 'true_false', 'difficulty' => 'easy', 'stem' => 'Risks can be positive.',
            'options' => [['id' => 'true', 'text' => 'True'], ['id' => 'false', 'text' => 'False']], 'correct_answer_ids' => ['true'], 'explanation_correct' => 'Opportunities.', 'status' => 'published']);
        $kept = $quiz->questions()->orderBy('quiz_questions.sort_order')->first();
        $this->get("/admin/lessons/{$lesson->id}/edit")->assertInertia(fn (AssertableInertia $page) => $page
            ->where('activity.questions.0.from_bank', false)->has('activity.questions', 2)->has('questionBank', 3));
        $this->put("/admin/lessons/{$lesson->id}", [
            'title' => 'Initiating check', 'content_type' => 'quiz', 'is_published' => true,
            'activity' => ['passing_score_pct' => 50, 'questions' => [
                [...$this->question('What formally authorizes a project?'), 'id' => $kept->id],
                ['id' => $bank->id, 'type' => 'true_false', 'stem' => 'edited text is ignored', 'options' => [['id' => 'true', 'text' => 'True'], ['id' => 'false', 'text' => 'False']], 'correct_answer_ids' => ['true']],
            ]],
        ])->assertSessionHasNoErrors();
        $this->assertSame(['What formally authorizes a project?', 'Risks can be positive.'], $quiz->fresh()->questions->pluck('stem')->all());
        $this->assertSame(1, Question::where('linked_lesson_id', $lesson->id)->count(), 'The removed inline question is retired.');

        $this->put("/admin/lessons/{$lesson->id}", ['title' => 'Broken', 'content_type' => 'quiz', 'is_published' => true,
            'activity' => ['questions' => [[...$this->question('No answer'), 'correct_answer_ids' => ['z']]]]])->assertSessionHasErrors('activity.questions.0');

        $learner = $this->publishAndEnroll($course);
        $this->actingAs($learner)->post("/lessons/{$lesson->id}/complete")->assertStatus(422);
        $this->get("/courses/{$course->slug}/lessons/{$lesson->id}")->assertInertia(fn (AssertableInertia $page) => $page
            ->where('activity.kind', 'quiz')->where('activity.quiz_id', $quiz->id)->where('activity.passed', false)->where('lesson.is_activity', true));

        $quiz = $quiz->fresh();
        $attempt = app(AssessmentAttemptService::class)->start($quiz, $learner, fn () => $quiz->getQuestionsForAttempt());
        $answers = $quiz->questions->map(fn ($q) => ['question_id' => $q->id, 'selected_ids' => $q->correct_answer_ids, 'time_spent' => 5])->values()->all();
        app(AssessmentAttemptService::class)->complete($attempt, $answers);
        $this->assertSame('completed', LessonProgress::where('user_id', $learner->id)->where('lesson_id', $lesson->id)->value('status'));
        $this->get("/quiz-attempts/{$attempt->id}/review")->assertInertia(fn (AssertableInertia $page) => $page
            ->where('lessonUrl', route('lessons.show', ['course' => $course->slug, 'lesson' => $lesson->id])));
    }

    public function test_assignment_submission_grading_and_resubmission(): void
    {
        Storage::fake('local');
        $facilitator = $this->actor('facilitator');
        [$course, $module] = $this->draftCourse($facilitator);
        $this->actingAs($facilitator)->post("/facilitator/courses/{$course->id}/modules/{$module->id}/lessons", [
            'title' => 'Draft a charter', 'content_type' => 'assignment', 'text_content' => '<p>Write a one-page project charter.</p>', 'is_published' => true,
            'activity' => ['max_score' => 50, 'passing_score' => 30, 'allow_file_upload' => true, 'max_resubmissions' => 1],
        ])->assertSessionHasNoErrors();
        $lesson = Lesson::where('title', 'Draft a charter')->firstOrFail();
        $assignment = Assignment::where('lesson_id', $lesson->id)->firstOrFail();
        $this->assertSame(50, $assignment->max_score);
        $this->assertSame([], app(CourseReadinessService::class)->issues($course->fresh()));

        $learner = $this->publishAndEnroll($course);
        $this->actingAs($learner)->post("/lessons/{$lesson->id}/submission", [])->assertSessionHasErrors('content');
        $this->post("/lessons/{$lesson->id}/submission", ['content' => 'My charter', 'files' => [UploadedFile::fake()->create('charter.pdf', 50, 'application/pdf')]])
            ->assertSessionHasNoErrors();
        $submission = $assignment->submissions()->firstOrFail();
        $this->assertSame('submitted', $submission->status);
        $this->assertSame('completed', LessonProgress::where('user_id', $learner->id)->where('lesson_id', $lesson->id)->value('status'));
        $this->get("/assignment-submissions/{$submission->id}/files/0")->assertOk()->assertDownload('charter.pdf');
        $this->post("/lessons/{$lesson->id}/submission", ['content' => 'Again'])->assertStatus(409);
        $this->actingAs(User::factory()->create())->get("/assignment-submissions/{$submission->id}/files/0")->assertForbidden();

        $this->actingAs($facilitator)->get("/authoring/lessons/{$lesson->id}/submissions")
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Authoring/Submissions')->has('submissions.data', 1));
        $this->get("/assignment-submissions/{$submission->id}/files/0")->assertOk();
        $this->put("/authoring/submissions/{$submission->id}/grade", ['score' => 60, 'status' => 'graded'])->assertSessionHasErrors('score');
        $this->put("/authoring/submissions/{$submission->id}/grade", ['score' => 20, 'feedback' => 'Add stakeholders.', 'status' => 'returned'])->assertSessionHasNoErrors();
        $this->actingAs($this->actor('facilitator'))->put("/authoring/submissions/{$submission->id}/grade", ['score' => 50, 'status' => 'graded'])->assertForbidden();

        $this->actingAs($learner)->post("/lessons/{$lesson->id}/submission", ['content' => 'Revised charter'])->assertSessionHasNoErrors();
        $this->assertSame('resubmitted', $assignment->submissions()->where('submission_number', 2)->value('status'));
        $this->post("/lessons/{$lesson->id}/submission", ['content' => 'Third'])->assertStatus(409);
    }

    public function test_case_study_requires_an_answer_for_every_question(): void
    {
        $admin = $this->actor('platform_admin');
        [$course, $module] = $this->draftCourse($admin);
        $this->actingAs($admin)->post("/admin/modules/{$module->id}/lessons", [
            'title' => 'Hospital rollout', 'content_type' => 'case_study', 'text_content' => '<p>A hospital is replacing its records system…</p>', 'is_published' => true,
            'activity' => ['max_score' => 100, 'prompts' => ['Identify the key risks.', '  ', 'Recommend a response strategy.']],
        ])->assertSessionHasNoErrors();
        $lesson = Lesson::where('title', 'Hospital rollout')->firstOrFail();
        $this->assertSame(['Identify the key risks.', 'Recommend a response strategy.'], $lesson->assignment->prompts);

        $learner = $this->publishAndEnroll($course);
        $this->actingAs($learner)->post("/lessons/{$lesson->id}/submission", ['answers' => ['Scope creep', '']])->assertSessionHasErrors('answers.1');
        $this->post("/lessons/{$lesson->id}/submission", ['answers' => ['Scope creep', 'Mitigate with change control']])->assertSessionHasNoErrors();
        $this->assertSame('Mitigate with change control', $lesson->assignment->submissions()->first()->answers[1]['answer']);
    }

    public function test_drip_tab_schedules_lessons_and_sequential_mode_locks_until_previous_completion(): void
    {
        $admin = $this->actor('platform_admin');
        [$course, $module] = $this->draftCourse($admin);
        $first = $module->lessons()->create(['title' => 'First', 'content_type' => 'text', 'text_content' => 'One', 'is_published' => true, 'sort_order' => 1]);
        $second = $module->lessons()->create(['title' => 'Second', 'content_type' => 'text', 'text_content' => 'Two', 'is_published' => true, 'sort_order' => 2]);
        $later = $course->modules()->create(['title' => 'Later', 'is_published' => true, 'sort_order' => 2])
            ->lessons()->create(['title' => 'Third', 'content_type' => 'text', 'text_content' => 'Three', 'is_published' => true, 'sort_order' => 1]);

        $this->actingAs($admin)->put("/authoring/courses/{$course->id}/drip", ['sequential_lessons' => true, 'lessons' => [
            ['id' => $first->id, 'unlock_type' => 'immediate'],
            ['id' => $second->id, 'unlock_type' => 'immediate'],
            ['id' => $later->id, 'unlock_type' => 'days_after_enrollment'],
        ]])->assertSessionHasErrors('lessons.2.unlock_days_after_enrollment');
        $this->put("/authoring/courses/{$course->id}/drip", ['sequential_lessons' => true, 'lessons' => [
            ['id' => $first->id, 'unlock_type' => 'immediate'],
            ['id' => $second->id, 'unlock_type' => 'immediate'],
            ['id' => $later->id, 'unlock_type' => 'days_after_enrollment', 'unlock_days_after_enrollment' => 7],
        ]])->assertSessionHasNoErrors();
        $this->assertTrue($course->fresh()->sequential_lessons);
        $this->assertSame(7, $later->fresh()->unlock_days_after_enrollment);
        $foreign = Course::factory()->create()->modules()->create(['title' => 'Other'])->lessons()->create(['title' => 'Foreign', 'content_type' => 'text']);
        $this->put("/authoring/courses/{$course->id}/drip", ['sequential_lessons' => false, 'lessons' => [['id' => $foreign->id, 'unlock_type' => 'immediate']]])->assertSessionHasErrors('lessons');
        $this->actingAs($this->actor('facilitator'))->put("/authoring/courses/{$course->id}/drip", ['sequential_lessons' => false, 'lessons' => []])->assertForbidden();

        $learner = $this->publishAndEnroll($course);
        $this->actingAs($learner)->get("/courses/{$course->slug}/lessons/{$second->id}")
            ->assertRedirect(route('lessons.show', ['course' => $course->slug, 'lesson' => $first->id]));
        $this->post("/lessons/{$second->id}/complete")->assertForbidden();
        $this->get("/courses/{$course->slug}/lessons/{$first->id}")->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('sequential', true)->where('nextLesson.can_access', false)->where('nextLesson.availability_label', 'Complete “First” first'));
        $this->post("/lessons/{$first->id}/complete")->assertSessionHasNoErrors();
        $this->get("/courses/{$course->slug}/lessons/{$second->id}")->assertOk();
        $this->post("/lessons/{$second->id}/complete");
        // The next module is still held by its drip delay even though the sequence allows it.
        $this->get("/courses/{$course->slug}/lessons/{$later->id}")->assertRedirect("/courses/{$course->slug}");
    }

    public function test_production_regressions_blank_duration_and_duplicate_course_titles(): void
    {
        $admin = $this->actor('platform_admin');
        [$course, $module] = $this->draftCourse($admin);
        // 8 Oct 2026 production errors: PDF lessons saved without a duration hit a NOT NULL column.
        $this->actingAs($admin)->post("/admin/modules/{$module->id}/lessons", ['title' => 'Blank duration', 'content_type' => 'text', 'text_content' => '<p>Body</p>', 'duration_minutes' => null, 'is_published' => true])->assertSessionHasNoErrors();
        $lesson = Lesson::where('title', 'Blank duration')->firstOrFail();
        $this->assertSame(10, $lesson->duration_minutes);
        $this->put("/admin/lessons/{$lesson->id}", ['title' => 'Blank duration', 'content_type' => 'text', 'text_content' => '<p>Body</p>', 'duration_minutes' => '', 'is_published' => true])->assertSessionHasNoErrors();
        $this->assertSame(10, $lesson->fresh()->duration_minutes);

        // Creating a course whose title matches an existing (even deleted) course must not crash.
        $category = Category::create(['name' => 'Projects', 'slug' => 'projects', 'is_active' => true]);
        $payload = ['title' => 'Test', 'category_id' => $category->id, 'level' => 'beginner', 'type' => 'self_paced', 'price' => 0];
        $this->post('/admin/courses', $payload)->assertSessionHasNoErrors();
        Course::where('slug', 'test')->firstOrFail()->delete();
        $this->post('/admin/courses', $payload)->assertSessionHasNoErrors();
        $this->post('/admin/courses', $payload)->assertSessionHasNoErrors();
        $this->assertSame(['test', 'test-2', 'test-3'], Course::withTrashed()->where('title', 'Test')->orderBy('id')->pluck('slug')->all());
    }

    public function test_video_lessons_accept_youtube_and_vimeo_links(): void
    {
        $admin = $this->actor('platform_admin');
        [, $module] = $this->draftCourse($admin);
        foreach (['https://youtu.be/dQw4w9WgXcQ?si=abc' => 'youtube', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' => 'youtube',
            'https://vimeo.com/76979871' => 'vimeo', 'https://cdn.example.test/intro.mp4' => 'url'] as $url => $provider) {
            $this->actingAs($admin)->post("/admin/modules/{$module->id}/lessons", ['title' => 'Video '.$provider, 'content_type' => 'video', 'video_url' => $url, 'is_published' => true])
                ->assertSessionHasNoErrors();
            $this->assertSame($provider, Lesson::latest('id')->first()->video_provider);
        }
        $this->post("/admin/modules/{$module->id}/lessons", ['title' => 'Bad', 'content_type' => 'video', 'video_url' => 'javascript:alert(1)'])->assertSessionHasErrors('video_url');
    }

    public function test_readiness_flags_empty_activities_and_switching_type_retires_the_quiz(): void
    {
        $admin = $this->actor('platform_admin');
        [$course, $module] = $this->draftCourse($admin);
        $this->actingAs($admin)->post("/admin/modules/{$module->id}/lessons", ['title' => 'Empty quiz', 'content_type' => 'quiz', 'is_published' => true, 'activity' => ['questions' => []]])->assertSessionHasNoErrors();
        $lesson = Lesson::where('title', 'Empty quiz')->firstOrFail();
        $this->assertContains('Add at least one question to the quiz “Empty quiz”.', app(CourseReadinessService::class)->issues($course->fresh()));
        $this->assertFalse($lesson->quiz->is_published);

        $this->put("/admin/lessons/{$lesson->id}", ['title' => 'Now an article', 'content_type' => 'text', 'text_content' => '<p>Body</p>', 'is_published' => true])->assertSessionHasNoErrors();
        $this->assertNull(Quiz::where('lesson_id', $lesson->id)->first());
    }
}
