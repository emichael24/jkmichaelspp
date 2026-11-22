<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\MediaAsset;
use App\Models\ScormProgress;
use App\Models\User;
use App\Services\LearningAccessService;
use App\Services\PrivateMediaService;
use App\Services\ProtectedDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PrivateMediaController extends Controller
{
    public function show(Request $request, MediaAsset $asset)
    {
        abort_if(config('media.require_scan') && ! $asset->scanned_at, 423, 'This file is awaiting its security scan.');
        if (app(ProtectedDocumentService::class)->supports($asset)) {
            $this->authorizeDocumentDownload($asset, $request->user());
        }
        $this->authorizeAsset($asset, $request->user());
        abort_unless(in_array($asset->kind, ['video', 'document', 'image', 'audio']), 404);
        if ($asset->kind === 'document' && ($asset->mime !== 'application/pdf' || $request->boolean('download'))) {
            return response()->download(Storage::disk('local')->path($asset->path), 'resource.'.match ($asset->mime) {
                'application/pdf' => 'pdf', 'text/plain' => 'txt', 'text/csv' => 'csv',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx', default => 'pptx',
            }, ['Content-Type' => $asset->mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
        }

        return response()->file(Storage::disk('local')->path($asset->path), [
            'Content-Type' => $asset->mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function launch(Request $request, Lesson $lesson)
    {
        app(LearningAccessService::class)->assertLesson($lesson, $request->user());
        $id = basename(parse_url($lesson->scorm_path ?? '', PHP_URL_PATH) ?? '');
        $asset = MediaAsset::where('kind', 'scorm')->findOrFail($id);
        $token = Crypt::encryptString(json_encode(['asset' => $asset->id, 'user' => $request->user()->id, 'lesson' => $lesson->id, 'expires' => time() + 7200]));

        return response()->json(['url' => url('/scorm-assets/'.rawurlencode($token).'/'.$asset->entry_point), 'channel' => hash('sha256', $token)]);
    }

    public function scorm(string $token, string $path, PrivateMediaService $media)
    {
        try {
            $claims = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            abort(403);
        }
        abort_unless(($claims['expires'] ?? 0) > time() && $media->safePath($path), 403);
        $user = User::where('is_active', true)->findOrFail($claims['user']);
        $lesson = Lesson::findOrFail($claims['lesson']);
        app(LearningAccessService::class)->assertLesson($lesson, $user);
        $asset = MediaAsset::where('kind', 'scorm')->findOrFail($claims['asset']);
        abort_if(config('media.require_scan') && ! $asset->scanned_at, 423, 'This package is awaiting its security scan.');
        abort_unless($lesson->scorm_path === $asset->url(), 403);
        $disk = Storage::disk('local');
        $file = $asset->path.'/'.$path;
        abort_unless($disk->exists($file), 404);
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $source = url('/scorm-assets/');
        $headers = [
            'Content-Security-Policy' => "sandbox allow-scripts; default-src 'none'; script-src 'unsafe-inline' 'unsafe-eval' $source; style-src 'unsafe-inline' $source; img-src data: $source; media-src $source; font-src data: $source; connect-src $source; frame-src $source; base-uri 'none'; form-action 'none'; frame-ancestors 'self'",
            'Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer', 'X-Content-Type-Options' => 'nosniff',
            'Access-Control-Allow-Origin' => 'null',
        ];
        if (in_array($extension, ['html', 'htm'])) {
            $progress = ScormProgress::where('user_id', $user->id)->where('lesson_id', $lesson->id)->first();
            $state = ['cmi.core.lesson_status' => $progress?->cmi_core_lesson_status ?? 'not attempted',
                'cmi.core.score.raw' => (string) ($progress?->cmi_core_score_raw ?? ''),
                'cmi.core.lesson_location' => $progress?->cmi_core_lesson_location ?? '',
                'cmi.suspend_data' => $progress?->suspend_data['data'] ?? ''];
            $bridge = view('scorm.bridge', ['state' => $state, 'channel' => hash('sha256', $token), 'targetOrigin' => request()->getSchemeAndHttpHost()])->render();

            return response($bridge.$disk->get($file), 200, ['Content-Type' => 'text/html; charset=UTF-8', ...$headers]);
        }
        $mime = match ($extension) {
            'js' => 'application/javascript', 'css' => 'text/css', 'json' => 'application/json', 'svg' => 'image/svg+xml', 'xml','xsd','dtd' => 'application/xml', 'woff' => 'font/woff', 'woff2' => 'font/woff2', default => $disk->mimeType($file)
        };

        return response()->file($disk->path($file), ['Content-Type' => $mime, ...$headers]);
    }

    private function authorizeDocumentDownload(MediaAsset $asset, User $user): void
    {
        if ($asset->user_id === $user->id || $user->hasRole('platform_admin')) {
            return;
        }
        foreach (\App\Models\Resource::where('file_url', $asset->url())->where('is_published', true)->with('course')->get() as $resource) {
            try {
                app(LearningAccessService::class)->assertResource($resource, $user);

                return;
            } catch (HttpException) {
            }
        }
        $lessons = Lesson::where('is_downloadable', true)->where(fn ($query) => $query->where('pdf_url', $asset->url())->orWhere('slides_url', $asset->url()))->get();
        foreach ($lessons as $lesson) {
            try {
                app(LearningAccessService::class)->assertLesson($lesson, $user);

                return;
            } catch (HttpException) {
            }
        }
        abort(403, 'Source downloads are disabled. Use the protected lesson reader.');
    }

    private function authorizeAsset(MediaAsset $asset, User $user): void
    {
        if ($asset->user_id === $user->id || $user->hasRole('platform_admin')) {
            return;
        }
        foreach (\App\Models\Resource::where('file_url', $asset->url())->where('is_published', true)->with('course')->get() as $resource) {
            try {
                app(LearningAccessService::class)->assertResource($resource, $user);

                return;
            } catch (HttpException) {
            }
        }
        $lessons = Lesson::where(fn ($q) => $q->where('video_url', $asset->url())->orWhere('scorm_path', $asset->url())
            ->orWhere('audio_url', $asset->url())->orWhere('pdf_url', $asset->url())->orWhere('slides_url', $asset->url())
            ->orWhere('text_content', 'like', '%/media/'.$asset->id.'%'))->get();
        foreach ($lessons as $lesson) {
            try {
                app(LearningAccessService::class)->assertLesson($lesson, $user, true);

                return;
            } catch (HttpException) {
            }
        }
        abort(403);
    }
}
