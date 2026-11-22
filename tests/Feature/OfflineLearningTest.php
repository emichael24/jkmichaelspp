<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfflineLearningTest extends TestCase
{
    use RefreshDatabase;

    private function reading(): array
    {
        $user = User::factory()->create();
        $course = Course::factory()->create(['is_published' => true]);
        $module = $course->modules()->create(['title' => 'Readings', 'is_published' => true]);
        $lesson = $module->lessons()->create(['title' => 'Risk decisions', 'content_type' => 'text', 'text_content' => '<p>Protected reading.</p>', 'is_published' => true, 'is_downloadable' => true]);
        $enrollment = $course->enrollments()->create(['user_id' => $user->id, 'status' => 'active', 'enrolled_at' => now()]);

        return [$user, $lesson, $enrollment];
    }

    private function event(User $user, Lesson $lesson): array
    {
        $payload = $this->actingAs($user)->getJson('/lessons/'.$lesson->id.'/offline')->assertOk()->json();
        $this->assertArrayNotHasKey('video_url', $payload);

        return ['event_id' => (string) Str::uuid(), 'owner' => $user->uuid, 'lesson_id' => $lesson->id, 'lease' => $payload['lease'], 'complete' => true, 'progress_pct' => 100, 'time_spent_seconds' => 0];
    }

    public function test_reading_completion_is_idempotent_and_payload_reuse_is_rejected(): void
    {
        [$user, $lesson] = $this->reading();
        $event = $this->event($user, $lesson);
        $this->postJson('/api/progress/sync', $event)->assertOk()->assertJsonPath('replayed', false);
        $this->postJson('/api/progress/sync', $event)->assertOk()->assertJsonPath('replayed', true);
        $this->postJson('/api/progress/sync', [...$event, 'progress_pct' => 80])->assertConflict();
        $this->assertDatabaseCount('offline_progress_events', 1);
        $this->assertDatabaseCount('certificates', 1);
        $this->assertDatabaseHas('lesson_progress', ['lesson_id' => $lesson->id, 'user_id' => $user->id, 'status' => 'completed']);
    }

    public function test_offline_lease_cannot_move_between_accounts_or_lessons_and_revocation_is_checked(): void
    {
        [$user, $lesson, $enrollment] = $this->reading();
        $event = $this->event($user, $lesson);
        $other = User::factory()->create();
        $this->actingAs($other)->postJson('/api/progress/sync', $event)->assertForbidden();
        $this->postJson('/api/progress/sync', [...$event, 'owner' => $other->uuid])->assertForbidden();
        $this->getJson('/lessons/'.$lesson->id.'/offline')->assertForbidden();
        $this->actingAs($user)->postJson('/api/progress/sync', [...$event, 'lesson_id' => $lesson->id + 1])->assertForbidden();
        $enrollment->update(['status' => 'suspended']);
        $this->postJson('/api/progress/sync', $event)->assertForbidden();
        $this->assertDatabaseCount('offline_progress_events', 0);
    }

    public function test_expired_tampered_and_download_disabled_permissions_fail_closed(): void
    {
        [$user, $lesson] = $this->reading();
        $event = $this->event($user, $lesson);
        $this->postJson('/api/progress/sync', [...$event, 'lease' => 'tampered'])->assertForbidden();
        $lesson->update(['is_downloadable' => false]);
        $this->getJson('/lessons/'.$lesson->id.'/offline')->assertForbidden();
        $this->postJson('/api/progress/sync', $event)->assertForbidden();
        $lesson->update(['is_downloadable' => true, 'scorm_path' => 'private/scorm']);
        $this->getJson('/lessons/'.$lesson->id.'/offline')->assertForbidden();
        $lesson->update(['scorm_path' => null]);
        $this->travel(25)->hours();
        $this->postJson('/api/progress/sync', $event)->assertStatus(410);
        $this->assertDatabaseCount('offline_progress_events', 0);
    }

    public function test_signout_instructs_browser_to_purge_private_storage(): void
    {
        $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/')->assertHeader('Clear-Site-Data', '"cache", "storage"');
        $this->assertGuest();
    }
}
