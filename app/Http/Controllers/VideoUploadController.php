<?php

namespace App\Http\Controllers;

use App\Services\PrivateMediaService;
use Illuminate\Http\Request;

class VideoUploadController extends Controller
{
    /**
     * Handle large chunked video uploads from the frontend
     * and optional transcoding.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'video' => 'required|file|mimetypes:video/mp4,video/quicktime|max:500000',
        ]);

        $asset = app(PrivateMediaService::class)->video($request->file('video'), $request->user());

        return response()->json([
            'status' => 'success',
            'video_url' => $asset->url(),
            'video_provider' => 's3',
        ]);
    }
}
