<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\CourseMediaController;
use App\Models\Course;
use App\Services\CurriculumRevisionService;
use App\Services\PrivateMediaService;
use Illuminate\Http\Request;

class AuthoringMediaController extends Controller
{
    public function store(Request $request, string $type, PrivateMediaService $media)
    {
        $request->validate(['course_id' => 'required|integer|exists:courses,id']);
        $course = Course::findOrFail($request->integer('course_id'));
        abort_unless($request->user()->hasRole('platform_admin') || $course->author_id === $request->user()->id, 403);
        app(CurriculumRevisionService::class)->assertEditable($course);
        abort_unless(in_array($type, ['video', 'audio', 'pdf', 'slides', 'image', 'scorm']), 404);

        if ($type === 'video') {
            return app(CourseMediaController::class)->uploadVideo($request, $media);
        }
        if ($type === 'scorm') {
            return app(CourseMediaController::class)->uploadScorm($request, $media);
        }

        $limit = match ($type) {
            'image' => 8192, 'audio' => 51200, default => 20480
        };
        $request->validate(['file' => 'required|file|max:'.$limit]);
        $asset = $media->authoringFile($request->file('file'), $request->user(), $type);

        return response()->json(['status' => 'success', 'url' => $asset->url(), 'mime' => $asset->mime,
            'bytes' => $asset->bytes, 'name' => $request->file('file')->getClientOriginalName()]);
    }
}
