<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\MediaAsset;
use App\Services\LearningAccessService;
use App\Services\ProtectedDocumentService;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ProtectedDocumentController extends Controller
{
    public function lesson(Request $request, Lesson $lesson, string $type, ProtectedDocumentService $documents)
    {
        $asset = $this->lessonAsset($request, $lesson, $type);
        $base = "/lessons/{$lesson->id}/documents/{$type}";

        return response()->json([...$this->manifest($request, $asset, $documents), 'page_url' => url($base.'/pages'), 'source_url' => url($base.'/source'),
            'download_url' => $lesson->is_downloadable ? url("/lessons/{$lesson->id}/download/{$type}") : null], 200, $this->headers());
    }

    public function lessonPage(Request $request, Lesson $lesson, string $type, int $page, ProtectedDocumentService $documents)
    {
        $asset = $this->lessonAsset($request, $lesson, $type);

        return $this->pageResponse($documents->page($asset, $page, $request->user()));
    }

    public function lessonSource(Request $request, Lesson $lesson, string $type, ProtectedDocumentService $documents)
    {
        return $this->sourceResponse($request, $this->lessonAsset($request, $lesson, $type), $documents);
    }

    public function preview(Request $request, MediaAsset $asset, ProtectedDocumentService $documents)
    {
        $this->authorizePreview($request, $asset);

        return response()->json([...$this->manifest($request, $asset, $documents), 'page_url' => url("/authoring/documents/{$asset->id}/pages"),
            'source_url' => url("/authoring/documents/{$asset->id}/source"), 'download_url' => null], 200, $this->headers());
    }

    public function previewPage(Request $request, MediaAsset $asset, int $page, ProtectedDocumentService $documents)
    {
        $this->authorizePreview($request, $asset);

        return $this->pageResponse($documents->page($asset, $page, $request->user()));
    }

    public function previewSource(Request $request, MediaAsset $asset, ProtectedDocumentService $documents)
    {
        $this->authorizePreview($request, $asset);

        return $this->sourceResponse($request, $asset, $documents);
    }

    /**
     * Prefer watermarked server-rendered pages; fall back to in-browser rendering when
     * the converters are not installed or cannot run, so lessons never dead-end.
     */
    private function manifest(Request $request, MediaAsset $asset, ProtectedDocumentService $documents): array
    {
        if ($documents->serverRenderingAvailable($asset)) {
            try {
                return ['mode' => 'raster', ...$documents->metadata($asset)];
            } catch (HttpExceptionInterface $exception) {
                if ($exception->getStatusCode() !== 503 || ! $documents->browserRenderingAllowed()) {
                    throw $exception;
                }
                logger()->warning('Server document rendering unavailable; using browser renderer', ['asset' => $asset->id]);
            }
        }
        abort_unless($documents->browserRenderingAllowed(), 503, 'The document reader is temporarily unavailable.');

        return $documents->browserManifest($asset, $request->user());
    }

    private function sourceResponse(Request $request, MediaAsset $asset, ProtectedDocumentService $documents)
    {
        abort_unless($documents->browserRenderingAllowed(), 404);
        // The reader fetches the source with XHR; plain navigation (save-link, address bar) is refused.
        abort_unless($request->header('X-Document-Reader') === '1', 403, 'Open this document in the lesson reader.');
        $source = $documents->source($asset);

        return response()->file($source['path'], ['Content-Type' => $source['mime'], 'Content-Disposition' => 'inline', ...$this->headers()]);
    }

    private function lessonAsset(Request $request, Lesson $lesson, string $type): MediaAsset
    {
        abort_unless(in_array($type, ['pdf', 'slides'], true), 404);
        app(LearningAccessService::class)->assertLesson($lesson, $request->user(), true);
        $url = $lesson->{$type.'_url'};
        abort_unless($url && preg_match('~^/media/([a-f0-9-]{36})$~i', parse_url($url, PHP_URL_PATH) ?? '', $match), 422, 'The author must upload this document to enable the protected reader.');

        return MediaAsset::findOrFail($match[1]);
    }

    private function authorizePreview(Request $request, MediaAsset $asset): void
    {
        abort_unless($request->user()->hasRole('platform_admin') || ($request->user()->hasRole('facilitator') && $asset->user_id === $request->user()->id), 403);
    }

    private function pageResponse(string $image)
    {
        return response($image, 200, ['Content-Type' => 'image/png', 'Content-Disposition' => 'inline', ...$this->headers()]);
    }

    private function headers(): array
    {
        return ['Cache-Control' => 'private, no-store, max-age=0', 'Pragma' => 'no-cache', 'X-Content-Type-Options' => 'nosniff',
            'Cross-Origin-Resource-Policy' => 'same-origin', 'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex, noarchive'];
    }
}
