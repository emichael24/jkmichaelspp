<?php

namespace Tests\Feature;

use App\Exceptions\ExternalIntegrationException;
use App\Models\AiChatSession;
use App\Models\AiJob;
use App\Models\Course;
use App\Models\User;
use App\Models\WhatsappSession;
use App\Services\ExternalApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AiWhatsAppIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.flexichurch.api_key' => 'integration-test-only',
            'services.flexichurch.media_base_url' => 'https://integration.example/storage',
            'services.flexichurch.base_url' => 'https://integration.example/api/v1/external']);
        $this->withoutVite();
    }

    private function signIn(string $role = 'platform_admin'): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function fake(string $path, array $data): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['https://integration.example/api/v1/external'.$path => Http::response(['status' => 'success', 'data' => $data])]);
    }

    private function chatSession(User $user, array $attributes = []): AiChatSession
    {
        return AiChatSession::create(['uuid' => (string) Str::uuid(), 'user_id' => $user->id,
            'title' => 'Study session', 'context_type' => 'general', 'messages' => [], ...$attributes]);
    }

    public function test_missing_configuration_is_reported_without_a_request_or_session_record(): void
    {
        $this->signIn();
        config(['services.flexichurch.api_key' => null]);
        $this->postJson('/admin/erp/whatsapp/sessions', ['session_name' => 'Support'])
            ->assertStatus(503)->assertJsonPath('message', 'The AI and WhatsApp integration is not configured. Contact your platform administrator.');
        Http::assertNothingSent();
        $this->assertDatabaseCount('whatsapp_sessions', 0);
    }

    public function test_pairing_retains_the_returned_identifier_and_uses_it_for_qr_and_status(): void
    {
        $this->signIn();
        $this->fake('/whatsapp/sessions', ['id' => 'opaque-remote-id', 'status' => 'WAITING_FOR_QR']);
        $this->postJson('/admin/erp/whatsapp/sessions', ['session_name' => 'Support'])
            ->assertOk()->assertJsonPath('session.id', 'opaque-remote-id');
        $this->assertDatabaseHas('whatsapp_sessions', ['session_id' => 'opaque-remote-id', 'name' => 'Support', 'webhook_url' => null]);
        Http::assertSent(fn ($request) => Str::startsWith($request['session_name'], 'jkm-') && ! isset($request['webhook_url'])
            && $request->hasHeader('Authorization', 'Bearer integration-test-only'));
        $this->fake('/whatsapp/sessions/opaque-remote-id/qr', ['qrCode' => 'data:image/png;base64,YWJj', 'qrExpiresAt' => now()->addMinute()->toIso8601String()]);
        $this->getJson('/admin/erp/whatsapp/sessions/opaque-remote-id/qr')->assertOk()->assertJsonPath('qrCode', 'data:image/png;base64,YWJj');
        $this->fake('/whatsapp/sessions/opaque-remote-id', ['status' => 'CONNECTED', 'phone' => '2348000000000', 'connectedAt' => now()->toIso8601String()]);
        $this->getJson('/admin/erp/whatsapp/sessions/opaque-remote-id/status')->assertOk();
        $this->assertDatabaseHas('whatsapp_sessions', ['session_id' => 'opaque-remote-id', 'status' => 'CONNECTED']);
    }

    public function test_unknown_gateway_sessions_cannot_be_read_or_disconnected(): void
    {
        $this->signIn();
        $this->getJson('/admin/erp/whatsapp/sessions/another-application/qr')->assertNotFound();
        $this->getJson('/admin/erp/whatsapp/sessions/another-application/status')->assertNotFound();
        $this->postJson('/admin/erp/whatsapp/sessions/another-application/logout')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_rejected_provider_key_is_sanitized_and_not_treated_as_a_created_session(): void
    {
        $this->signIn();
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['status' => 'error', 'message' => 'secret upstream diagnostic'], 401)]);
        $response = $this->postJson('/admin/erp/whatsapp/sessions', ['session_name' => 'Support'])->assertStatus(503);
        $this->assertStringNotContainsString('secret upstream diagnostic', $response->getContent());
        $this->assertDatabaseCount('whatsapp_sessions', 0);
        Http::assertSentCount(1);
    }

    public function test_malformed_success_does_not_disconnect_an_existing_session(): void
    {
        $this->signIn();
        WhatsappSession::create(['session_id' => 'mine', 'name' => 'Main', 'status' => 'CONNECTED']);
        $this->fake('/whatsapp/sessions/mine', []);
        $this->getJson('/admin/erp/whatsapp/sessions/mine/status')->assertStatus(502);
        $this->assertDatabaseHas('whatsapp_sessions', ['session_id' => 'mine', 'status' => 'CONNECTED']);
    }

    public function test_learner_cannot_use_admin_pairing_or_facilitator_media_endpoints(): void
    {
        $this->signIn('learner');
        $this->postJson('/admin/erp/whatsapp/sessions', ['session_name' => 'Bad'])->assertForbidden();
        $this->postJson('/facilitator/ai-studio/tts', ['text' => 'Hello', 'voice_id' => 'aaliyah'])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_tutor_create_read_and_chat_routes_persist_a_real_exchange(): void
    {
        $this->signIn('learner');
        $this->fake('/ai/chat', ['content' => 'Scope describes the work.']);
        $id = $this->postJson('/ai/tutor/sessions', ['context_type' => 'general'])->assertOk()->json('session.id');
        $this->postJson("/ai/tutor/sessions/$id/chat", ['message' => 'Explain project scope'])->assertOk()->assertJsonPath('response', 'Scope describes the work.');
        $this->getJson("/ai/tutor/sessions/$id")->assertOk()->assertJsonPath('session.messages.0.content', 'Explain project scope')
            ->assertJsonPath('session.messages.1.role', 'assistant')->assertJsonPath('session.title', 'Explain project scope');
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/ai/chat') && $request['fast'] === true);
    }

    public function test_tutor_sessions_are_private_and_archived_chats_cannot_continue(): void
    {
        $user = $this->signIn('learner');
        $other = $this->chatSession(User::factory()->create());
        $this->getJson("/ai/tutor/sessions/$other->id")->assertForbidden();
        $this->postJson("/ai/tutor/sessions/$other->id/chat", ['message' => 'Explain'])->assertForbidden();
        $mine = $this->chatSession($user, ['is_archived' => true]);
        $this->postJson("/ai/tutor/sessions/$mine->id/chat", ['message' => 'Explain'])->assertStatus(409);
        Http::assertNothingSent();
    }

    public function test_concurrent_chat_request_is_rejected_before_inference(): void
    {
        $session = $this->chatSession($this->signIn('learner'));
        $lock = Cache::lock('ai-chat:'.$session->id, 120);
        $lock->get();
        try {
            $this->postJson("/ai/tutor/sessions/$session->id/chat", ['message' => 'Explain'])->assertStatus(409);
        } finally {
            $lock->release();
        }
        Http::assertNothingSent();
    }

    public function test_chat_history_respects_provider_limits_and_failure_preserves_history(): void
    {
        $session = $this->chatSession($this->signIn('learner'), ['messages' => array_fill(0, 20, ['role' => 'user', 'content' => str_repeat('History ', 500)])]);
        $this->fake('/ai/chat', ['content' => 'A concise reply']);
        $this->postJson("/ai/tutor/sessions/$session->id/chat", ['message' => str_repeat('x', 7000)])->assertOk();
        Http::assertSent(fn ($request) => mb_strlen($request['message']) <= 8000);
        $this->assertCount(22, $session->fresh()->messages);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['status' => 'error'], 429)]);
        $this->postJson("/ai/tutor/sessions/$session->id/chat", ['message' => 'One more'])->assertStatus(429);
        $this->assertCount(22, $session->fresh()->messages);
    }

    public function test_text_summary_and_practice_quiz_match_workspace_contracts(): void
    {
        $this->signIn('learner');
        $this->fake('/ai/chat', ['content' => 'A useful summary.']);
        $this->postJson('/ai/lesson-summary', ['content' => 'A lesson about project planning and scope.', 'type' => 'brief'])->assertOk()->assertJsonPath('summary', 'A useful summary.');
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['status' => 'success', 'data' => ['content' => json_encode([['question' => 'What is scope?', 'options' => ['Work', 'Date', 'Cost', 'Risk'], 'correct_index' => '0', 'explanation' => 'Scope defines work.']])]])]);
        $this->postJson('/ai/quiz', ['content' => 'Scope defines the work in a project.', 'num_questions' => 1, 'difficulty' => 'easy'])->assertOk()->assertJsonPath('questions.0.correct_index', 0);
    }

    public function test_recommendations_use_enrolled_courses_without_an_invalid_sql_join(): void
    {
        $user = $this->signIn('learner');
        $course = Course::factory()->create(['title' => 'Project planning', 'is_published' => true]);
        $user->enrolledCourses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);
        $this->fake('/ai/chat', ['content' => 'Practice project planning.']);
        $this->getJson('/ai/recommendations')->assertOk();
        Http::assertSent(fn ($request) => str_contains($request['message'], 'Project planning'));
    }

    public function test_tts_uses_managed_voice_and_returns_playable_absolute_media(): void
    {
        $this->signIn('facilitator');
        $this->fake('/ai/tts', ['audio_path' => 'media/tts/voice.wav']);
        $this->postJson('/facilitator/ai-studio/tts', ['text' => 'Welcome to your course.', 'voice_id' => 'aaliyah', 'speed' => 1])
            ->assertOk()->assertJsonPath('audio_url', 'https://integration.example/storage/media/tts/voice.wav')->assertJsonPath('job.status', 'completed');
        Http::assertSent(fn ($request) => $request['voice_id'] === 'aaliyah' && ! isset($request['voice']));
        $this->assertDatabaseHas('ai_jobs', ['job_type' => 'tts', 'status' => 'completed']);
    }

    public function test_avatar_uploads_use_documented_engine_and_encoded_fields(): void
    {
        $this->signIn('facilitator');
        $this->fake('/ai/avatar', ['video_path' => 'media/avatars/video.mp4']);
        $audio = UploadedFile::fake()->createWithContent('voice.wav', 'RIFF'.pack('V', 36 + 1600).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16).'data'.pack('V', 1600).str_repeat("\0", 1600));
        $this->post('/facilitator/ai-studio/avatar', ['photo' => UploadedFile::fake()->image('portrait.png'), 'audio' => $audio, 'engine' => 'live-avatar'], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('result.video_url', 'https://integration.example/storage/media/avatars/video.mp4');
        Http::assertSent(fn ($request) => $request['engine'] === 'live-avatar' && isset($request['photo_base64'], $request['audio_base64'], $request['prompt']) && ! isset($request['photo']));
        $this->assertStringNotContainsString('base64', json_encode(AiJob::first()->input_payload));
    }

    public function test_transcription_accepts_generated_audio_url_and_rejects_private_urls(): void
    {
        $this->signIn('facilitator');
        $this->fake('/ai/transcribe', ['text' => 'Project planning starts with scope.']);
        $this->postJson('/facilitator/ai-studio/transcribe', ['audio_url' => 'http://127.0.0.1/private'])->assertUnprocessable();
        Http::assertNothingSent();
        $this->postJson('/facilitator/ai-studio/transcribe', ['audio_url' => 'https://integration.example/storage/media/tts/voice.wav'])
            ->assertOk()->assertJsonPath('text', 'Project planning starts with scope.');
    }

    public function test_media_errors_are_visible_and_jobs_fail_without_raw_provider_diagnostics(): void
    {
        $this->signIn('facilitator');
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['status' => 'error', 'message' => 'private detail'], 429)]);
        $this->postJson('/facilitator/ai-studio/tts', ['text' => 'Hello', 'voice_id' => 'orion'])->assertStatus(429);
        $job = AiJob::firstOrFail();
        $this->assertSame('failed', $job->status);
        $this->assertStringNotContainsString('private detail', $job->error_message);
    }

    public function test_media_options_and_voice_cloning_fail_clearly_before_contacting_provider(): void
    {
        $this->signIn('facilitator');
        $this->postJson('/facilitator/ai-studio/tts', ['text' => 'Hello', 'voice_id' => 'default'])->assertUnprocessable();
        $this->postJson('/facilitator/ai-studio/avatar', ['engine' => 'sadtalker'])->assertUnprocessable();
        $this->postJson('/facilitator/ai-studio/voice-clone', [])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_studio_history_includes_transcription_but_hides_other_users_jobs(): void
    {
        $user = $this->signIn('facilitator');
        foreach ([$user, User::factory()->create()] as $owner) {
            AiJob::create(['uuid' => (string) Str::uuid(), 'user_id' => $owner->id, 'job_type' => 'transcription', 'status' => 'completed', 'output' => 'Transcript']);
        }
        $this->get('/facilitator/ai-studio')->assertInertia(fn (Assert $page) => $page->component('Facilitator/AI/ContentStudio')->has('recentJobs', 1)->where('recentJobs.0.output', 'Transcript'));
        $this->getJson('/facilitator/ai-studio/jobs/'.AiJob::where('user_id', '!=', $user->id)->first()->id)->assertForbidden();
    }

    public function test_admin_content_and_insights_use_selected_tone_and_focus(): void
    {
        $this->signIn();
        $this->fake('/ai/chat', ['content' => 'Generated content']);
        $this->postJson('/admin/erp/ai/generate-content', ['type' => 'announcement', 'context' => 'A new project management course is ready.', 'tone' => 'friendly'])->assertOk()->assertJsonPath('content', 'Generated content');
        Http::assertSent(fn ($request) => str_contains($request['system_prompt'], 'friendly'));
        $this->postJson('/admin/erp/ai/platform-insights', ['type' => 'retention'])->assertOk()->assertJsonPath('insights', 'Generated content');
        Http::assertSent(fn ($request) => str_contains($request['message'], 'platform retention'));
        $this->get('/admin/erp/ai')->assertInertia(fn (Assert $page) => $page->component('Admin/AI/Insights')->has('recentInsights', 1)->where('recentInsights.0.output', 'Generated content'));
    }

    public function test_live_provider_storage_bucket_is_supported_without_allowing_arbitrary_urls(): void
    {
        config(['services.flexichurch.media_base_url' => 'https://storage.googleapis.com/flexichurch-church-uploads']);
        $client = app(ExternalApiClient::class);
        $url = 'https://storage.googleapis.com/flexichurch-church-uploads/media/tts/sample.wav';
        $this->assertSame($url, $client->mediaUrl($url));
        $this->assertSame($url, $client->mediaUrl('media/tts/sample.wav'));
        config(['services.flexichurch.media_base_url' => '']);
        $this->assertSame('https://integration.example/storage/media/tts/sample.wav', $client->mediaUrl('media/tts/sample.wav'));
        foreach (['https://storage.googleapis.com/other-bucket/media/tts/a.wav', 'http://127.0.0.1/a.wav', 'https://integration.example/storage/media/../private.wav', 'https://integration.example:8443/storage/media/a.wav', 'https://integration.example@evil.test/storage/media/a.wav', 'javascript:alert(1)'] as $badUrl) {
            try {
                $client->mediaUrl($badUrl);
                $this->fail('Unexpected media URL accepted');
            } catch (ExternalIntegrationException $e) {
                $this->assertSame(502, $e->httpStatus);
            }
        }
    }

    public function test_partial_generated_quiz_is_rejected_without_saving_any_questions(): void
    {
        $user = $this->signIn('facilitator');
        $course = Course::factory()->create(['author_id' => $user->id, 'is_published' => false]);
        $this->fake('/ai/chat', ['content' => json_encode([
            ['stem' => 'Valid question', 'options' => ['A', 'B', 'C', 'D'], 'correct_index' => 0, 'explanation' => 'Reason'],
            ['stem' => 'Invalid question', 'options' => ['A'], 'correct_index' => 9, 'explanation' => 'Reason'],
        ])]);
        $this->postJson('/facilitator/ai-studio/generate-quiz', ['course_id' => $course->id, 'domain' => 'Planning', 'content_text' => 'A lesson on project planning and scope.', 'count' => 2])
            ->assertStatus(502);
        $this->assertDatabaseCount('questions', 0);
    }

    public function test_transcription_upload_is_encoded_without_publishing_a_file(): void
    {
        $this->signIn('facilitator');
        $this->fake('/ai/transcribe', ['text' => 'Uploaded narration']);
        $audio = UploadedFile::fake()->createWithContent('voice.wav', 'RIFF'.pack('V', 36 + 1600).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16).'data'.pack('V', 1600).str_repeat("\0", 1600));
        $this->post('/facilitator/ai-studio/transcribe', ['audio' => $audio], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('text', 'Uploaded narration');
        Http::assertSent(fn ($request) => isset($request['audio_base64']) && ! isset($request['audio_url']));
    }

    public function test_provider_timeout_has_a_safe_error_without_automatic_post_retries(): void
    {
        $this->signIn('facilitator');
        Http::fake(fn () => throw new ConnectionException('token=private upstream request'));
        $response = $this->postJson('/facilitator/ai-studio/tts', ['text' => 'Hello', 'voice_id' => 'aaliyah'])->assertStatus(503);
        $this->assertStringNotContainsString('private upstream', $response->getContent());
        $this->assertDatabaseHas('ai_jobs', ['status' => 'failed']);
    }

    public function test_disconnect_requires_provider_acknowledgment(): void
    {
        $this->signIn();
        WhatsappSession::create(['session_id' => 'mine', 'name' => 'Main', 'status' => 'CONNECTED']);
        $this->fake('/whatsapp/sessions/mine/logout', []);
        $this->postJson('/admin/erp/whatsapp/sessions/mine/logout')->assertStatus(502);
        $this->assertDatabaseHas('whatsapp_sessions', ['session_id' => 'mine', 'status' => 'CONNECTED']);
        $this->fake('/whatsapp/sessions/mine/logout', ['status' => 'DISCONNECTED']);
        $this->postJson('/admin/erp/whatsapp/sessions/mine/logout')->assertOk();
        $this->assertDatabaseHas('whatsapp_sessions', ['session_id' => 'mine', 'status' => 'DISCONNECTED']);
    }
}
