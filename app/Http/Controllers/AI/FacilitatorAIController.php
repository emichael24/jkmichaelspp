<?php

namespace App\Http\Controllers\AI;

use App\Exceptions\ExternalIntegrationException;
use App\Http\Controllers\Controller;
use App\Models\AiJob;
use App\Models\Lesson;
use App\Services\AiMediaInput;
use App\Services\AIService;
use App\Services\ExternalApiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class FacilitatorAIController extends Controller
{
    public function __construct(protected AIService $ai) {}

    public function index()
    {
        return Inertia::render('Facilitator/AI/ContentStudio', [
            'recentJobs' => AiJob::where('user_id', auth()->id())
                ->whereIn('job_type', ['tts', 'avatar_video', 'content_repurpose', 'transcription'])
                ->latest()->limit(10)->get()->map(fn ($job) => $this->presentJob($job)),
        ]);
    }

    public function generateTTS(Request $request)
    {
        $data = $request->validate([
            'text' => 'required|string|max:10000',
            'voice_id' => 'required|in:aaliyah,angelo,helena,orion',
            'language' => 'sometimes|required|string|max:10',
            'speed' => 'sometimes|required|numeric|min:0.5|max:2',
        ]);

        return $this->runJob('tts', $request->only(['voice_id', 'language', 'speed']), function () use ($data) {
            $url = $this->ai->textToSpeech($data['text'], $data['voice_id'], $data['language'] ?? 'en', $data['speed'] ?? 1.0);

            return ['audio_url' => $url, 'audio_path' => $url];
        });
    }

    public function cloneVoice(Request $request)
    {
        throw new ExternalIntegrationException('Voice cloning is not supported by this integration. Choose a managed voice in Text-to-Speech.', 422);
    }

    public function generateAvatar(Request $request, AiMediaInput $media)
    {
        $data = $request->validate([
            'photo' => 'required_without:photo_url|nullable|file|mimetypes:image/jpeg,image/png,image/webp|max:5120',
            'photo_url' => 'required_without:photo|nullable|url|max:500',
            'audio' => 'required_without:audio_url|nullable|file|mimetypes:audio/mpeg,audio/wav,audio/x-wav,audio/ogg,audio/mp4,audio/x-m4a,audio/flac,audio/x-flac,audio/webm,video/webm|max:10240',
            'audio_url' => 'required_without:audio|nullable|url|max:500',
            'engine' => 'required|in:live-avatar,stable-avatar,ai-avatar',
            'prompt' => 'nullable|string|max:2000',
            'render_tier' => 'sometimes|required|in:standard,premium,ultra',
            'still_mode' => 'sometimes|boolean',
            'expression_scale' => 'sometimes|numeric|min:0.5|max:1.5',
            'photo_base64' => 'prohibited', 'audio_base64' => 'prohibited',
        ]);
        $payload = [...$request->only(['engine', 'render_tier', 'still_mode', 'expression_scale']),
            'prompt' => $data['prompt'] ?? 'A professional instructor presenting naturally to camera, with subtle movement and a clear, welcoming expression.',
            ...$media->fromRequest($request, 'photo'), ...$media->fromRequest($request, 'audio')];

        return $this->runJob('avatar_video', ['engine' => $data['engine']], fn () => ['result' => $this->ai->generateAvatarVideo($payload)]);
    }

    public function repurposeContent(Request $request)
    {
        $data = $request->validate([
            'content' => 'required|string|min:50|max:7000',
            'target_format' => 'required|in:video_script,blog_post,social_linkedin,social_twitter,email_lesson,infographic_outline,podcast_script',
            'tone' => 'sometimes|required|in:professional,casual,encouraging,academic',
            'lesson_id' => 'nullable|exists:lessons,id',
        ]);
        if ($request->filled('lesson_id')) {
            $lesson = Lesson::with('module.course')->findOrFail($data['lesson_id']);
            abort_unless($request->user()->hasRole('platform_admin') || $lesson->module->course->author_id === $request->user()->id, 403);
        }

        return $this->runJob('content_repurpose', $request->only(['target_format', 'tone', 'lesson_id']),
            fn () => ['result' => $this->ai->repurposeContent($data['content'], $data['target_format'], $data['tone'] ?? 'professional')]);
    }

    public function transcribeAudio(Request $request, AiMediaInput $media)
    {
        $request->validate([
            'audio' => 'required_without:audio_url|nullable|file|mimetypes:audio/mpeg,audio/wav,audio/x-wav,audio/ogg,audio/mp4,audio/x-m4a,audio/flac,audio/x-flac,audio/webm,video/webm|max:10240',
            'audio_url' => 'required_without:audio|nullable|url|max:500',
            'audio_base64' => 'prohibited',
        ]);
        $payload = $media->fromRequest($request, 'audio');

        return $this->runJob('transcription', [], fn () => ['text' => $this->ai->transcribeAudio($payload['audio_url'] ?? '', $payload['audio_base64'] ?? null)]);
    }

    public function jobStatus(AiJob $job)
    {
        abort_unless($job->user_id === auth()->id(), 403);

        return response()->json(['job' => $this->presentJob($job)]);
    }

    private function runJob(string $type, array $metadata, callable $operation)
    {
        $job = AiJob::create(['uuid' => (string) Str::uuid(), 'user_id' => auth()->id(), 'job_type' => $type,
            'status' => 'processing', 'input_payload' => $metadata]);
        try {
            $result = $operation();
            $job->update(['status' => 'completed', 'completed_at' => now(),
                'output_file_path' => $result['audio_url'] ?? (is_array($result['result'] ?? null) ? ($result['result']['video_url'] ?? null) : null),
                'output' => isset($result['result']) ? (is_string($result['result']) ? $result['result'] : json_encode($result['result'])) : ($result['text'] ?? null)]);

            return response()->json(['job' => $this->presentJob($job->fresh()), ...$result]);
        } catch (ExternalIntegrationException $e) {
            $job->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            throw $e;
        } catch (\Throwable $e) {
            $job->update(['status' => 'failed', 'error_message' => 'Generation could not finish. Please retry or contact support.']);
            throw $e;
        }
    }

    private function presentJob(AiJob $job): array
    {
        $data = $job->only(['id', 'uuid', 'job_type', 'status', 'output', 'created_at', 'error_message']);
        $data['output_file_path'] = null;
        if ($job->output_file_path) {
            try {
                $data['output_file_path'] = app(ExternalApiClient::class)->mediaUrl($job->output_file_path);
            } catch (ExternalIntegrationException) {
                $data['error_message'] = 'This older media link is unavailable. Generate a new version.';
            }
        }

        return $data;
    }
}
