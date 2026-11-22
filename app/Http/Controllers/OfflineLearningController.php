<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\User;
use App\Services\CourseCompletionService;
use App\Services\LearningAccessService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class OfflineLearningController extends Controller
{
    public function session(Request $request)
    {
        return response()->json(['owner' => $request->user()->uuid, 'context' => hash_hmac('sha256', $request->session()->getId(), config('app.key'))])->header('Cache-Control', 'private, no-store');
    }

    public function download(Request $request, Lesson $lesson)
    {
        $this->authorizeDownload($request, $lesson);
        $expires = now()->addHours(24);
        $lease = Crypt::encryptString(json_encode(['user_id' => $request->user()->id, 'lesson_id' => $lesson->id, 'expires' => $expires->timestamp], JSON_THROW_ON_ERROR));

        return response()->json([
            'lessonId' => $lesson->id, 'owner' => $request->user()->uuid,
            'title' => $lesson->title, 'course' => $lesson->module->course->title,
            'summary' => $lesson->summary, 'text_content' => $lesson->text_content,
            'key_terms' => $lesson->key_terms, 'expiresAt' => $expires->getTimestampMs(), 'lease' => $lease,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function sync(Request $request)
    {
        $data = $request->validate([
            'event_id' => 'required|uuid', 'owner' => 'required|uuid', 'lesson_id' => 'required|integer',
            'lease' => 'required|string|max:2000', 'complete' => 'required|boolean',
            'progress_pct' => 'required|integer|min:0|max:100', 'time_spent_seconds' => 'required|integer|min:0|max:86400',
        ]);
        abort_unless(hash_equals($request->user()->uuid, $data['owner']), 403, 'Saved progress belongs to a different account.');
        try {
            $lease = json_decode(Crypt::decryptString($data['lease']), true, 10, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException $e) {
            abort(403, 'The offline permission is invalid.');
        }
        abort_unless(($lease['user_id'] ?? null) === $request->user()->id && ($lease['lesson_id'] ?? null) === $data['lesson_id'], 403);
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
        $result = DB::transaction(function () use ($request, $data, $lease, $hash) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $existing = DB::table('offline_progress_events')->where('user_id', $request->user()->id)->where('event_id', $data['event_id'])->first();
            if ($existing) {
                abort_unless(hash_equals($existing->payload_hash, $hash), 409, 'This event was already used for different progress.');

                return ['accepted' => true, 'replayed' => true];
            }
            abort_unless(($lease['expires'] ?? 0) > now()->timestamp, 410, 'The offline permission expired. Reopen the lesson online.');
            $lesson = Lesson::findOrFail($data['lesson_id']);
            $this->authorizeDownload($request, $lesson);
            $progress = app(CourseCompletionService::class)->progress($request->user(), $lesson, $data, $data['complete']);
            DB::table('offline_progress_events')->insert([
                'user_id' => $request->user()->id, 'event_id' => $data['event_id'], 'payload_hash' => $hash,
                'created_at' => now(),
            ]);

            return ['accepted' => true, 'replayed' => false, 'status' => $progress->status];
        }, 3);

        return response()->json($result)->header('Cache-Control', 'private, no-store');
    }

    private function authorizeDownload(Request $request, Lesson $lesson): void
    {
        app(LearningAccessService::class)->assertLesson($lesson, $request->user());
        abort_unless($lesson->is_downloadable && ! $lesson->scorm_path, 403, 'Offline reading is disabled for this lesson.');
        abort_unless($lesson->text_content || $lesson->summary, 422, 'This lesson has no reading material to save.');
    }
}
