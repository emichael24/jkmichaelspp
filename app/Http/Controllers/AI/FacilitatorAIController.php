<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\AiJob;
use App\Models\VoiceClone;
use App\Models\Lesson;
use App\Services\AIService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class FacilitatorAIController extends Controller
{
    public function __construct(protected AIService $ai) {}

    // ─── AI Content Studio page ───────────────────────────────────────────────────
    public function index()
    {
        $recentJobs = AiJob::where('user_id', auth()->id())
            ->whereIn('job_type', ['tts', 'avatar_video', 'content_repurpose'])
            ->latest()
            ->limit(10)
            ->get();

        $voiceClones = VoiceClone::where('user_id', auth()->id())
            ->where('is_active', true)
            ->get();

        return Inertia::render('Facilitator/AI/ContentStudio', [
            'recentJobs'  => $recentJobs,
            'voiceClones' => $voiceClones,
        ]);
    }

    // ─── Text-to-Speech ───────────────────────────────────────────────────────────
    public function generateTTS(Request $request)
    {
        $request->validate([
            'text'     => 'required|string|max:10000',
            'voice_id' => 'required|string',
            'language' => 'nullable|string|max:10',
            'speed'    => 'nullable|numeric|min:0.5|max:2.0',
        ]);

        $job = AiJob::create([
            'uuid'          => (string) Str::uuid(),
            'user_id'       => auth()->id(),
            'job_type'      => 'tts',
            'status'        => 'processing',
            'input_payload' => $request->only(['text', 'voice_id', 'language', 'speed']),
        ]);

        try {
            $audioPath = $this->ai->textToSpeech(
                $request->input('text'),
                $request->input('voice_id'),
                $request->input('language', 'en'),
                $request->input('speed', 1.0),
            );

            $job->update([
                'status'           => 'completed',
                'output_file_path' => $audioPath,
                'completed_at'     => now(),
            ]);

            return response()->json(['job' => $job->fresh(), 'audio_path' => $audioPath]);
        } catch (\Throwable $e) {
            $job->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    // ─── Voice Cloning ────────────────────────────────────────────────────────────
    public function cloneVoice(Request $request)
    {
        $request->validate([
            'audio_url' => 'required|url|max:500',
            'name'      => 'required|string|max:100',
        ]);

        $result = $this->ai->cloneVoice(
            $request->input('audio_url'),
            $request->input('name'),
        );

        $voiceClone = VoiceClone::create([
            'user_id'          => auth()->id(),
            'name'             => $request->input('name'),
            'voice_id'         => $result['voice_id'],
            'sample_audio_url' => $request->input('audio_url'),
        ]);

        return response()->json(['voice_clone' => $voiceClone]);
    }

    // ─── Avatar Video Generation ──────────────────────────────────────────────────
    public function generateAvatar(Request $request)
    {
        $request->validate([
            'photo_url'        => 'nullable|url|max:500',
            'photo_base64'     => 'nullable|string',
            'audio_url'        => 'nullable|url|max:500',
            'audio_base64'     => 'nullable|string',
            'engine'           => 'nullable|in:sadtalker,liveportrait',
            'resolution'       => 'nullable|in:256,512',
            'still_mode'       => 'nullable|boolean',
            'expression_scale' => 'nullable|numeric|min:0.1|max:3.0',
        ]);

        // Must provide at least photo and audio via URL or base64
        if (! $request->filled('photo_url') && ! $request->filled('photo_base64')) {
            return response()->json(['error' => 'Provide photo_url or photo_base64.'], 422);
        }
        if (! $request->filled('audio_url') && ! $request->filled('audio_base64')) {
            return response()->json(['error' => 'Provide audio_url or audio_base64.'], 422);
        }

        $job = AiJob::create([
            'uuid'          => (string) Str::uuid(),
            'user_id'       => auth()->id(),
            'job_type'      => 'avatar_video',
            'status'        => 'processing',
            'input_payload' => $request->except(['photo_base64', 'audio_base64']), // don't store large base64
        ]);

        try {
            $result = $this->ai->generateAvatarVideo($request->all());

            $job->update([
                'status'           => 'completed',
                'output_file_path' => $result['video_path'] ?? null,
                'output'           => json_encode($result),
                'processing_seconds' => (int) ($result['video_duration'] ?? 0),
                'completed_at'     => now(),
            ]);

            return response()->json(['job' => $job->fresh(), 'result' => $result]);
        } catch (\Throwable $e) {
            $job->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    // ─── Content Repurposing ──────────────────────────────────────────────────────
    public function repurposeContent(Request $request)
    {
        $request->validate([
            'content'       => 'required|string|min:50',
            'target_format' => 'required|in:video_script,blog_post,social_linkedin,social_twitter,email_lesson,infographic_outline,podcast_script',
            'tone'          => 'nullable|in:professional,casual,encouraging,academic',
            'lesson_id'     => 'nullable|exists:lessons,id',
        ]);

        $job = AiJob::create([
            'uuid'          => (string) Str::uuid(),
            'user_id'       => auth()->id(),
            'job_type'      => 'content_repurpose',
            'status'        => 'processing',
            'input_payload' => $request->only(['target_format', 'tone', 'lesson_id']),
        ]);

        try {
            $result = $this->ai->repurposeContent(
                $request->input('content'),
                $request->input('target_format'),
                $request->input('tone', 'professional'),
            );

            $job->update([
                'status'       => 'completed',
                'output'       => $result,
                'completed_at' => now(),
            ]);

            return response()->json(['job' => $job->fresh(), 'result' => $result]);
        } catch (\Throwable $e) {
            $job->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    // ─── Transcribe Audio (for turning recordings into lesson content) ────────────
    public function transcribeAudio(Request $request)
    {
        $request->validate([
            'audio_url' => 'required|url|max:500',
        ]);

        $job = AiJob::create([
            'uuid'          => (string) Str::uuid(),
            'user_id'       => auth()->id(),
            'job_type'      => 'transcription',
            'status'        => 'processing',
            'input_payload' => ['audio_url' => $request->input('audio_url')],
        ]);

        try {
            $text = $this->ai->transcribeAudio($request->input('audio_url'));

            $job->update([
                'status'       => 'completed',
                'output'       => $text,
                'completed_at' => now(),
            ]);

            return response()->json(['job' => $job->fresh(), 'text' => $text]);
        } catch (\Throwable $e) {
            $job->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    // ─── Job status polling ───────────────────────────────────────────────────────
    public function jobStatus(AiJob $job)
    {
        abort_unless($job->user_id === auth()->id(), 403);
        return response()->json(['job' => $job]);
    }
}
