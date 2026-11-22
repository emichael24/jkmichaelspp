<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\InternshipEnrollment;
use App\Models\InternshipSubmission;
use App\Models\InternshipTask;
use App\Models\InternshipTrack;
use App\Models\Organization;
use App\Models\SponsorReview;
use App\Models\SponsorScorecard;
use App\Models\User;
use App\Services\CertificateService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InternshipIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function internship(): array
    {
        $org = Organization::create(['name' => 'Internship '.bin2hex(random_bytes(3)), 'contact_email' => 'internship@example.test', 'max_seats' => 10]);
        $learner = User::factory()->create(['organization_id' => $org->id]);
        $sponsor = User::factory()->create(['organization_id' => $org->id]);
        $sponsor->assignRole(Role::findOrCreate('sponsor', 'web'));
        $track = InternshipTrack::create(['title' => 'Delivery', 'slug' => 'delivery-'.bin2hex(random_bytes(4)), 'duration_weeks' => 8, 'price' => 0,
            'review_rubric' => ['Quality', 'Application']]);
        $enrollment = InternshipEnrollment::create(['user_id' => $learner->id, 'sponsor_id' => $sponsor->id, 'track_id' => $track->id,
            'start_date' => today(), 'end_date' => today()->addWeeks(8), 'status' => 'active']);
        $milestone = $track->milestones()->create(['title' => 'Evidence', 'week_number' => 1]);
        $task = $enrollment->tasks()->create(['title' => 'Analysis', 'type' => 'weekly', 'milestone_id' => $milestone->id]);

        return [$learner, $sponsor, $track, $enrollment, $task];
    }

    private function submit(User $learner, InternshipTask $task): InternshipSubmission
    {
        $this->actingAs($learner)->post('/internship/tasks/'.$task->id.'/submit', ['content' => 'My evidence-based analysis'])->assertRedirect()->assertSessionHasNoErrors();

        return $task->submissions()->latest('id')->firstOrFail();
    }

    private function approval(): array
    {
        return ['decision' => 'approved', 'overall_score' => 0, 'rubric_scores' => ['Quality' => ['score' => 4, 'comment' => 'Clear work'], 'Application' => ['score' => 5]]];
    }

    public function test_learners_cannot_self_complete_tasks_or_submit_empty_duplicate_or_closed_work(): void
    {
        [$learner, , , $enrollment, $task] = $this->internship();
        $this->actingAs($learner)->put('/internship/tasks/'.$task->id.'/status', ['status' => 'done'])->assertSessionHasErrors('status');
        $this->post('/internship/tasks/'.$task->id.'/submit', [])->assertSessionHasErrors('content');
        $this->assertDatabaseCount('internship_submissions', 0);
        $this->submit($learner, $task);
        $this->post('/internship/tasks/'.$task->id.'/submit', ['content' => 'Repeated click'])->assertConflict();
        $this->put('/internship/tasks/'.$task->id.'/status', ['status' => 'backlog'])->assertConflict();
        $this->assertDatabaseCount('internship_submissions', 1);
        $enrollment->update(['status' => 'withdrawn']);
        $this->post('/internship/tasks/'.$task->id.'/submit', ['content' => 'Closed'])->assertConflict();
    }

    public function test_review_requires_complete_bounded_rubric_and_issues_one_evidenced_credential(): void
    {
        [$learner, $sponsor, $track, $enrollment, $task] = $this->internship();
        $submission = $this->submit($learner, $task);
        $url = '/sponsor/submissions/'.$submission->id.'/review';
        $this->actingAs($sponsor)->get('/sponsor/submissions/'.$submission->id)->assertOk();
        $this->post($url, ['decision' => 'approved'])->assertSessionHasErrors('rubric_scores');
        $bad = $this->approval();
        $bad['rubric_scores']['Quality']['score'] = 6;
        $this->post($url, $bad)->assertSessionHasErrors('rubric_scores.Quality.score');
        $this->assertDatabaseCount('sponsor_reviews', 0);
        $this->post($url, $this->approval())->assertSessionHasNoErrors();
        $this->assertSame('completed', $enrollment->fresh()->status);
        $this->assertSame('4.50', $enrollment->fresh()->overall_score);
        $this->assertEquals(4.5, SponsorReview::firstOrFail()->overall_score);
        $credential = Certificate::firstOrFail();
        $this->assertSame([$task->id], $credential->metadata['task_ids']);
        $this->assertSame([$submission->latestReview->id], $credential->metadata['review_ids']);
        $this->post($url, ['decision' => 'revision_requested'])->assertConflict();
        $this->assertSame('approved', $submission->fresh()->status);
        $this->assertSame($credential->id, app(CertificateService::class)->issue($learner, $track)->id);
        $this->assertDatabaseCount('certificates', 1);
        $this->actingAs($learner)->get('/internship')->assertOk()->assertInertia(fn ($page) => $page->where('enrollment.status', 'completed'));
        $this->get('/internship/submissions')->assertOk();
    }

    public function test_resubmission_preserves_old_versions_and_rejects_stale_decisions(): void
    {
        [$learner, $sponsor, , , $task] = $this->internship();
        $original = $this->submit($learner, $task);
        $this->actingAs($sponsor)->post('/sponsor/submissions/'.$original->id.'/review', ['decision' => 'revision_requested', 'feedback' => 'Provide more evidence'])->assertSessionHasNoErrors();
        $this->actingAs($learner)->post('/internship/submissions/'.$original->id.'/resubmit', ['content' => 'Improved version'])->assertSessionHasNoErrors();
        $current = $task->submissions()->latest('submission_number')->firstOrFail();
        $this->assertSame(2, $current->submission_number);
        $this->assertSame('revision_requested', $original->fresh()->status);
        $this->assertSame('My evidence-based analysis', $original->fresh()->content);
        $this->post('/internship/submissions/'.$original->id.'/resubmit', ['content' => 'Stale repeated click'])->assertConflict();
        $this->actingAs($sponsor)->post('/sponsor/submissions/'.$original->id.'/review', $this->approval())->assertConflict();
        $this->post('/sponsor/submissions/'.$current->id.'/review', $this->approval())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('internship_submissions', 2);
        $this->assertDatabaseCount('sponsor_reviews', 2);
    }

    public function test_legacy_done_flags_and_missing_milestones_cannot_issue_credentials(): void
    {
        [$learner, $sponsor, $track, $enrollment, $task] = $this->internship();
        $task->update(['status' => 'done']);
        $this->assertNull(app(CertificateService::class)->checkInternshipCompletion($enrollment));
        $track->milestones()->create(['title' => 'Final delivery', 'week_number' => 8]);
        $task->update(['status' => 'in_progress']);
        $submission = $this->submit($learner, $task);
        $this->actingAs($sponsor)->post('/sponsor/submissions/'.$submission->id.'/review', $this->approval())->assertSessionHasNoErrors();
        $this->assertSame('active', $enrollment->fresh()->status);
        $this->assertDatabaseCount('certificates', 0);
    }

    public function test_cross_tenant_assignment_and_self_review_do_not_grant_sponsor_access(): void
    {
        [$learner, $sponsor, , $enrollment, $task] = $this->internship();
        [$otherLearner, $otherSponsor] = $this->internship();
        $submission = $this->submit($learner, $task);
        $enrollment->update(['sponsor_id' => $otherSponsor->id]);
        $this->actingAs($otherSponsor)->get('/sponsor/submissions/'.$submission->id)->assertForbidden();
        $this->post('/sponsor/submissions/'.$submission->id.'/review', $this->approval())->assertForbidden();
        $this->get('/sponsor/review-queue')->assertInertia(fn ($page) => $page->has('submissions.data', 0));
        $this->actingAs($otherLearner)->get('/internship/submissions/'.$submission->id)->assertForbidden();
        $enrollment->update(['sponsor_id' => $learner->id]);
        $learner->assignRole('sponsor');
        $this->actingAs($learner)->post('/sponsor/submissions/'.$submission->id.'/review', $this->approval())->assertForbidden();
    }

    public function test_scorecards_compute_their_average_and_reject_repeated_periods_and_invalid_scales(): void
    {
        [, $sponsor, , $enrollment] = $this->internship();
        $payload = ['enrollment_id' => $enrollment->id, 'period_type' => 'weekly', 'period_number' => 1, 'overall_score' => 5,
            'scores' => ['quality' => 1, 'timeliness' => 2, 'communication' => 3, 'initiative' => 4, 'technical_skill' => 5]];
        $this->actingAs($sponsor)->post('/sponsor/scorecards', $payload)->assertSessionHasNoErrors();
        $this->assertEquals(3, SponsorScorecard::firstOrFail()->overall_score);
        $this->assertSame('3.00', $enrollment->fresh()->overall_score);
        $this->post('/sponsor/scorecards', $payload)->assertConflict();
        $payload['period_number'] = 2;
        $payload['scores']['quality'] = 0;
        $this->post('/sponsor/scorecards', $payload)->assertSessionHasErrors('scores.quality');
        $this->assertDatabaseCount('sponsor_scorecards', 1);
    }

    public function test_database_rejects_duplicate_credential_identity(): void
    {
        [$learner, , $track] = $this->internship();
        $certificate = app(CertificateService::class)->issue($learner, $track);
        $this->expectException(UniqueConstraintViolationException::class);
        $duplicate = $certificate->replicate(['uuid', 'certificate_number']);
        $duplicate->save();
    }

    public function test_administrators_cannot_manually_complete_unreviewed_work_or_delete_submitted_evidence(): void
    {
        [$learner, , , $enrollment, $task] = $this->internship();
        $platform = User::factory()->create();
        $platform->assignRole(Role::findOrCreate('platform_admin', 'web'));
        $this->actingAs($platform)->put('/admin/enrollments/'.$enrollment->id, ['status' => 'completed'])->assertSessionHasErrors('status');
        $this->assertSame('active', $enrollment->fresh()->status);
        $this->submit($learner, $task);
        $this->actingAs($platform)->delete('/admin/tasks/'.$task->id)->assertConflict();
        $this->assertDatabaseHas('internship_tasks', ['id' => $task->id]);
    }
}
