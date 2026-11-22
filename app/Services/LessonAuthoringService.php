<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\MediaAsset;
use App\Models\Module;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LessonAuthoringService
{
    public function validate(Request $request, bool $isUpdate = false): array
    {
        $validated = $request->validate([...app(LessonActivityService::class)->rules(),
            'title' => 'required|string|max:255',
            'content_type' => 'required|in:'.implode(',', Lesson::CONTENT_TYPES),
            'text_content' => 'nullable|string',
            'summary' => 'nullable|string',
            'key_terms' => 'nullable|array|max:100',
            'key_terms.*.term' => 'required|string|max:255',
            'key_terms.*.definition' => 'nullable|string|max:5000',
            'video_url' => 'nullable|url',
            'video_duration_seconds' => 'nullable|integer|min:0',
            'video_transcript' => 'nullable|string',
            'audio_url' => 'nullable|url',
            'audio_duration_seconds' => 'nullable|integer|min:0',
            'pdf_url' => 'nullable|string',
            'slides_url' => 'nullable|string',
            'scorm_path' => 'nullable|string|max:500',
            'scorm_version' => 'nullable|in:1.2,2004',
            'duration_minutes' => 'nullable|integer|min:0',
            'is_free_preview' => 'boolean',
            'is_downloadable' => 'boolean',
            'unlock_type' => 'nullable|in:immediate,date,days_after_enrollment',
            'unlock_at' => 'required_if:unlock_type,date|nullable|date',
            'unlock_days_after_enrollment' => 'required_if:unlock_type,days_after_enrollment|nullable|integer|min:1|max:365',
            'is_published' => $isUpdate ? 'boolean' : 'sometimes|boolean',
        ]);

        foreach (['video_url', 'audio_url', 'pdf_url', 'slides_url', 'scorm_path'] as $field) {
            $url = $validated[$field] ?? null;
            if (! $url) {
                continue;
            }
            if (preg_match('~/media/([a-f0-9-]{36})$~i', $url, $matches)) {
                $asset = MediaAsset::findOrFail($matches[1]);
                abort_unless($asset->user_id === $request->user()->id || $request->user()->hasRole('platform_admin'), 403);
                $valid = match ($field) {
                    'scorm_path' => $asset->kind === 'scorm',
                    'video_url' => $asset->kind === 'video',
                    'audio_url' => $asset->kind === 'audio',
                    'pdf_url' => $asset->kind === 'document' && $asset->mime === 'application/pdf',
                    'slides_url' => $asset->kind === 'document' && in_array($asset->mime, ['application/pdf', 'application/vnd.openxmlformats-officedocument.presentationml.presentation']),
                };
                if (! $valid) {
                    throw ValidationException::withMessages([$field => 'Choose an upload that matches this content format.']);
                }
                $validated[$field] = $asset->url();
            } else {
                if (in_array($field, ['pdf_url', 'slides_url'], true)) {
                    throw ValidationException::withMessages([$field => 'Upload the PDF or PowerPoint file to use the protected reader. Public document links cannot be protected.']);
                }
                if ($field === 'scorm_path' || ! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['https', 'http'])) {
                    throw ValidationException::withMessages([$field => 'Use an approved media upload or a valid HTTPS media URL.']);
                }
            }
        }

        // Uploaded images embedded by the visual editor retain the same ownership
        // boundary as the main lesson media; a guessed asset ID is never enough.
        preg_match_all('~/media/([a-f0-9-]{36})~i', $validated['text_content'] ?? '', $matches);
        foreach (array_unique($matches[1]) as $id) {
            $asset = MediaAsset::findOrFail($id);
            abort_unless($asset->user_id === $request->user()->id || $request->user()->hasRole('platform_admin'), 403);
        }

        // The column is required; a blank estimate keeps the stored value (or the default on create).
        if (array_key_exists('duration_minutes', $validated) && $validated['duration_minutes'] === null) {
            unset($validated['duration_minutes']);
        }

        $validated['unlock_type'] = $validated['unlock_type'] ?? 'immediate';

        if ($validated['unlock_type'] !== 'date') {
            $validated['unlock_at'] = null;
        }

        if ($validated['unlock_type'] !== 'days_after_enrollment') {
            $validated['unlock_days_after_enrollment'] = null;
        }

        return $validated;
    }

    /**
     * Create or update a lesson together with its quiz/assignment settings so
     * one "Save lesson" never leaves the lesson and its activity out of step.
     */
    public function persist(Module $module, ?Lesson $lesson, array $validated, User $actor): Lesson
    {
        $module->loadMissing('course');
        $activity = $validated['activity'] ?? null;
        unset($validated['activity']);
        app(LessonActivityService::class)->assertValid($validated['content_type'], $activity, $module->course);

        return DB::transaction(function () use ($module, $lesson, $validated, $activity, $actor) {
            if ($lesson) {
                $lesson->update($validated);
            } else {
                $lesson = $module->lessons()->create([
                    ...$validated,
                    'sort_order' => ($module->lessons()->max('sort_order') ?? 0) + 1,
                    'is_published' => $validated['is_published'] ?? true,
                ]);
            }
            $lesson->setRelation('module', $module);
            app(LessonActivityService::class)->sync($lesson->fresh(['module.course', 'quiz', 'assignment']), $activity, $actor);

            return $lesson;
        });
    }
}
