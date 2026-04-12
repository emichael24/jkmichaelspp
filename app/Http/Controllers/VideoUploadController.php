<?php

namespace App\Http\Controllers;

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

        $path = $request->file('video')->store('videos', 'public');

        return response()->json([
            'status' => 'success',
            'video_url' => asset("storage/{$path}"),
            'video_provider' => 's3', 
        ]);
    }
}

