<?php

namespace Tests\Feature;

use App\Models\ContentVersion;
use App\Models\Course;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use App\Services\ContentWorkflowService;
use App\Services\CurriculumRevisionService;
use App\Services\QuestionDefinitionValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContentGovernanceTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function curriculum(User $author): Course
    {
        $course = Course::factory()->create(['author_id' => $author->id, 'created_by_role' => 'facilitator', 'is_published' => false, 'published_at' => null, 'approval_status' => 'draft']);
        $module = $course->modules()->create(['title' => 'Foundations', 'is_published' => true]);
        $lesson = $module->lessons()->create(['title' => 'Scope', 'content_type' => 'text', 'text_content' => 'Original curriculum', 'is_published' => true]);
        $question = Question::factory()->create(['course_id' => $course->id, 'linked_lesson_id' => $lesson->id]);
        $quiz = Quiz::create(['course_id' => $course->id, 'module_id' => $module->id, 'lesson_id' => $lesson->id, 'title' => 'Check', 'type' => 'lesson_check', 'question_count' => 1, 'is_published' => true]);
        $quiz->questions()->attach($question->id, ['sort_order' => 1]);

        return $course;
    }

    public function test_review_publishes_a_deep_immutable_edition_and_revision_preserves_learner_content(): void
    {
        $author = $this->actor('facilitator');
        $reviewer = $this->actor('platform_admin');
        $course = $this->curriculum($author);
        $workflow = app(ContentWorkflowService::class);
        $approval = $workflow->submitForReview($course, $author);
        $this->assertSame('pending_review', $course->fresh()->approval_status);
        $this->actingAs($author)->post('/facilitator/courses/'.$course->id.'/modules', ['title' => 'Sneaked-in content'])->assertForbidden();
        $workflow->approve($approval, $reviewer, 'Reviewed', true);
        $version = ContentVersion::firstOrFail();
        $this->assertSame('Original curriculum', $version->data['modules'][0]['lessons'][0]['text_content']);
        $this->assertCount(1, $version->data['quizzes'][0]['questions']);
        $this->assertTrue($course->fresh()->is_published);
        $this->actingAs($reviewer)->put('/admin/modules/'.$course->modules()->first()->id, ['title' => 'Changed'])->assertForbidden();
        $this->actingAs($author)->post('/facilitator/courses/'.$course->id.'/revisions')->assertRedirect();
        $draft = Course::where('revision_of_id', $course->id)->firstOrFail();
        $this->assertFalse($draft->is_published);
        $this->assertSame('draft', $draft->approval_status);
        $this->assertSame($draft->lessons()->first()->id, Question::where('course_id', $draft->id)->first()->linked_lesson_id);
        $draftQuiz = Quiz::where('course_id', $draft->id)->firstOrFail();
        $this->assertSame($draft->id, $draftQuiz->questions()->first()->course_id);
        $draft->lessons()->first()->update(['text_content' => 'Revised curriculum']);
        $this->assertSame('Original curriculum', $course->lessons()->first()->text_content);
        $this->post('/facilitator/courses/'.$course->id.'/revisions')->assertRedirect();
        $this->assertSame(1, Course::where('revision_of_id', $course->id)->count());
        $this->expectException(\LogicException::class);
        $version->update(['data' => []]);
    }

    public function test_review_cannot_be_self_approved_replayed_or_applied_to_changed_content(): void
    {
        $author = $this->actor('facilitator');
        $reviewer = $this->actor('sme_reviewer');
        $course = $this->curriculum($author);
        $workflow = app(ContentWorkflowService::class);
        $approval = $workflow->submitForReview($course, $author);
        $this->actingAs($reviewer)->post('/sme/review/'.$approval->id.'/approve', ['publish' => true])->assertSessionHasNoErrors();
        $this->post('/sme/review/'.$approval->id.'/reject', ['notes' => 'A repeated decision'])->assertStatus(409);
        $next = $this->curriculum($reviewer);
        $approval = $workflow->submitForReview($next, $reviewer);
        $this->post('/sme/review/'.$approval->id.'/approve')->assertForbidden();
        $course = $this->curriculum($author);
        $approval = $workflow->submitForReview($course, $author);
        $course->lessons()->first()->update(['text_content' => 'Changed by a stale writer']);
        $this->post('/sme/review/'.$approval->id.'/approve')->assertStatus(409);
        $this->assertSame('pending', $approval->fresh()->status);
        app(CurriculumRevisionService::class)->recall($course, $author);
        $this->assertSame('draft', $course->fresh()->approval_status);
        $this->assertSame('changes_requested', $approval->fresh()->status);
    }

    public function test_question_publication_uses_status_and_invalid_keys_are_rejected(): void
    {
        $author = $this->actor('platform_admin');
        $reviewer = $this->actor('sme_reviewer');
        $course = $this->curriculum($author);
        $question = Question::where('course_id', $course->id)->firstOrFail();
        $question->update(['status' => 'draft']);
        $workflow = app(ContentWorkflowService::class);
        $approval = $workflow->submitForReview($question, $author);
        $workflow->approve($approval, $reviewer, null, true);
        $this->assertSame('published', $question->fresh()->status);
        $this->assertSame(1, ContentVersion::forContent(Question::class, $question->id)->count());
        $invalid = $question->attributesToArray();
        $invalid['correct_answer_ids'] = ['missing-option'];
        $this->expectException(ValidationException::class);
        app(QuestionDefinitionValidator::class)->validate($invalid);
    }
}
