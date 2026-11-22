<?php

namespace App\Services;

use App\Exceptions\ExternalIntegrationException;
use Illuminate\Support\Facades\Validator;

class AIService
{
    public function __construct(protected ExternalApiClient $api) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Chat / Text
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Single-turn chat completion.
     */
    public function chat(string $message, string $systemPrompt = '', float $temperature = 0.7, int $maxTokens = 2048, bool $fast = false): string
    {
        Validator::make(['message' => $message], ['message' => 'required|string|max:8000'])->validate();
        $data = $this->api->request('POST', '/ai/chat', ['message' => $message, 'system_prompt' => $systemPrompt,
            'temperature' => $temperature, 'max_tokens' => min(4096, max(1, $maxTokens)), 'fast' => $fast]);
        if (! is_string($data['content'] ?? null) || trim($data['content']) === '') {
            throw new ExternalIntegrationException('The AI service returned an empty response. Please retry.', 502);
        }

        return $data['content'];
    }

    /**
     * Generate embeddings for semantic search / RAG.
     */
    public function embed(string $text): array
    {
        $data = $this->api->request('POST', '/ai/embed', ['input' => $text]);
        if (! is_array($data['embedding'] ?? null) || ! count($data['embedding'])) {
            throw new ExternalIntegrationException('The AI service returned no embedding.', 502);
        }

        return $data['embedding'];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Lesson Tools
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Summarize lesson content.
     *
     * @param  string  $type  brief | detailed | key_points | study_guide
     */
    public function summarizeLesson(string $lessonContent, string $title, string $type = 'key_points'): string
    {
        $this->validateStudyText($lessonContent);
        $prompts = [
            'brief' => 'Summarize the following lesson in 3-5 concise sentences.',
            'detailed' => 'Write a comprehensive summary of the following lesson, covering all key concepts.',
            'key_points' => 'Extract the most important key points from the following lesson as a numbered list.',
            'study_guide' => 'Create a structured study guide from the following lesson with sections: Overview, Key Concepts, Important Details, Review Questions.',
        ];

        $systemPrompt = 'You are an expert educational content strategist for JK Michaels Professional Mastery Platform. '.
            "Produce high-quality, professional {$type} summaries suitable for working professionals and exam candidates.";

        $message = ($prompts[$type] ?? $prompts['key_points'])."\n\nLesson Title: {$title}\n\nContent:\n{$lessonContent}";

        return $this->chat($message, $systemPrompt, 0.5, 2048);
    }

    /**
     * Generate quiz questions from lesson content.
     */
    public function generateQuizFromLesson(string $lessonContent, string $title, int $questionCount = 5): array
    {
        $this->validateStudyText($lessonContent);
        $systemPrompt = 'You are an expert exam question writer for JK Michaels Professional Mastery Platform. '.
            'Generate professional multiple-choice questions. Return ONLY valid JSON — no extra text.';

        $message = "Create {$questionCount} multiple-choice quiz questions based on this lesson.\n\n".
            'Return a JSON array where each item has: question (string), options (array of 4 strings), '.
            "correct_index (0-3), explanation (string).\n\n".
            "Lesson: {$title}\n\n{$lessonContent}";

        $raw = $this->chat($message, $systemPrompt, 0.6, 4096);

        // Extract JSON from response (model may wrap in markdown fences)
        if (preg_match('/```(?:json)?\s*([\s\S]+?)```/', $raw, $m)) {
            $raw = $m[1];
        }

        $questions = json_decode(trim($raw), true);

        if (! is_array($questions)) {
            throw new ExternalIntegrationException('The AI returned an invalid quiz. Please generate it again.', 502);
        }

        $validator = Validator::make(['questions' => $questions], [
            'questions' => 'required|array|list|size:'.$questionCount,
            'questions.*.question' => 'required|string|max:4000', 'questions.*.options' => 'required|array|list|size:4',
            'questions.*.options.*' => 'required|string|max:2000', 'questions.*.correct_index' => 'required|integer|min:0|max:3',
            'questions.*.explanation' => 'required|string|max:6000',
        ]);
        if ($validator->fails()) {
            throw new ExternalIntegrationException('The AI returned an incomplete quiz. Please generate it again.', 502);
        }

        return array_map(fn ($question) => [...$question, 'correct_index' => (int) $question['correct_index']], $questions);
    }

    private function validateStudyText(string $content): void
    {
        Validator::make(['content' => $content], ['content' => 'required|string|min:20|max:7000'], [
            'content.required' => 'This lesson has no text to analyze. Paste a transcript in AI Tutor.',
            'content.max' => 'This lesson is too long for one request. Paste a section of up to 7,000 characters in AI Tutor.',
        ])->validate();
    }

    /**
     * Repurpose a lesson into a different format (e.g., script for video, blog post, social post).
     */
    public function repurposeContent(string $content, string $targetFormat, string $tone = 'professional'): string
    {
        $formatInstructions = [
            'video_script' => 'a clear, engaging video narration script with speaker notes and timing cues',
            'blog_post' => 'an SEO-optimized educational blog post with headings and practical examples',
            'social_linkedin' => 'a compelling LinkedIn post (max 3000 chars) highlighting 3 key takeaways',
            'social_twitter' => 'a Twitter/X thread (max 15 tweets) breaking down the key concepts',
            'email_lesson' => 'an email-based micro-lesson with a clear subject line and CTA',
            'infographic_outline' => 'a structured outline for an infographic with sections and bullet points',
            'podcast_script' => 'a podcast episode script with intro, main discussion, and outro',
        ];

        $instruction = $formatInstructions[$targetFormat] ?? "a {$targetFormat}";

        $systemPrompt = 'You are a professional content strategist and learning designer for JK Michaels. '.
            "Repurpose educational content with {$tone} tone for maximum impact.";

        $message = "Repurpose the following learning content into {$instruction}.\n\nOriginal Content:\n{$content}";

        return $this->chat($message, $systemPrompt, 0.75, 3000);
    }

    /**
     * AI-powered analytics insights from data.
     */
    public function generateInsights(array $analyticsData, string $context = 'platform'): string
    {
        $systemPrompt = 'You are a data analyst specializing in learning management systems. '.
            'Provide clear, actionable business insights from LMS analytics data.';

        $message = "Analyze the following {$context} analytics data and provide:\n".
            "1. Key findings (3-5 bullet points)\n".
            "2. Concerning trends to address\n".
            "3. Actionable recommendations\n\n".
            "Data:\n".json_encode($analyticsData, JSON_PRETTY_PRINT);

        return $this->chat($message, $systemPrompt, 0.5, 2000);
    }

    /**
     * Generate personalized learning recommendations for a student.
     */
    public function getPersonalizedRecommendations(array $learnerProfile): string
    {
        $systemPrompt = 'You are a personal learning coach at JK Michaels. '.
            'Provide concise, motivating, personalized study guidance.';

        $message = "Based on this learner's profile and performance data, provide 3-5 personalized study recommendations:\n\n".
            json_encode($learnerProfile, JSON_PRETTY_PRINT);

        return $this->chat($message, $systemPrompt, 0.7, 1000, true);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Text-to-Speech
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Convert text to speech audio.
     * Returns the file path/URL of the generated audio.
     */
    public function textToSpeech(string $text, string $voiceId, string $language = 'en', float $speed = 1.0): string
    {
        $data = $this->api->request('POST', '/ai/tts', ['text' => $text, 'voice_id' => $voiceId, 'language' => $language, 'speed' => $speed], 180);

        return $this->api->mediaUrl($data['audio_url'] ?? $data['audio_path'] ?? '');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Avatar Video
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Generate a talking-head avatar video.
     *
     * @param  array  $params  photo_url|photo_base64, audio_url|audio_base64, engine, resolution, still_mode, expression_scale
     * @return array video_path, video_duration, file_size_bytes
     */
    public function generateAvatarVideo(array $params): array
    {
        $data = $this->api->request('POST', '/ai/avatar', $params, 180);
        $data['video_url'] = $this->api->mediaUrl($data['video_url'] ?? $data['video_path'] ?? '');

        return $data;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Transcription
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Transcribe audio to text.
     */
    public function transcribeAudio(string $audioUrl = '', ?string $audioBase64 = null): string
    {
        $payload = $audioBase64 ? ['audio_base64' => $audioBase64] : ['audio_url' => $audioUrl];
        $data = $this->api->request('POST', '/ai/transcribe', [...$payload, 'format' => 'verbose_json', 'language' => 'en'], 180);
        if (! is_string($data['text'] ?? null)) {
            throw new ExternalIntegrationException('The AI service returned no transcription.', 502);
        }

        return $data['text'];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Voice Cloning
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Clone a voice from a sample audio.
     */
    public function cloneVoice(string $audioUrl, string $name): array
    {
        throw new ExternalIntegrationException('Voice cloning is not available through this integration. Choose a managed voice in Text-to-Speech.', 422);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Service Status
    // ─────────────────────────────────────────────────────────────────────────

    public function status(): bool
    {
        try {
            return ($this->api->request('GET', '/ai/status', [], 10)['configured'] ?? false) === true;
        } catch (ExternalIntegrationException) {
            return false;
        }
    }
}
