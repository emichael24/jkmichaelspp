<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Services\PrivateMediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CourseMediaController extends Controller
{
    public function uploadVideo(Request $request, PrivateMediaService $media)
    {
        $data = $request->validate([
            'file' => 'required|file|max:5120', 'fileName' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'chunkIndex' => 'required|integer|min:0|max:99', 'totalChunks' => 'required|integer|min:1|max:100',
        ]);
        abort_unless($data['chunkIndex'] < $data['totalChunks'], 422);
        $disk = Storage::disk('local');
        $directory = 'upload-parts/'.$request->user()->id.'/'.hash('sha256', $data['fileName']);
        $disk->makeDirectory($directory);
        $lock = fopen($disk->path($directory.'/lock'), 'c');
        abort_unless($lock && flock($lock, LOCK_EX), 503);
        try {
            $metaPath = $directory.'/state.json';
            $state = $disk->exists($metaPath) ? json_decode($disk->get($metaPath), true, 512, JSON_THROW_ON_ERROR) : ['chunks' => (int) $data['totalChunks'], 'created' => time()];
            abort_unless($state['chunks'] === (int) $data['totalChunks'], 409, 'Upload parameters changed. Start a new upload.');
            if (isset($state['asset'])) {
                return $this->videoResponse(MediaAsset::findOrFail($state['asset']));
            }
            abort_unless(time() - $state['created'] < 86400, 410, 'Upload expired. Start again.');
            $part = $directory.'/'.$data['chunkIndex'].'.part';
            $source = $request->file('file')->getRealPath();
            if ($disk->exists($part)) {
                abort_unless(hash_equals(hash_file('sha256', $disk->path($part)), hash_file('sha256', $source)), 409, 'A different chunk already occupies this position.');
            } else {
                $stream = fopen($source, 'rb');
                try {
                    abort_unless($disk->put($part, $stream), 503);
                } finally {
                    fclose($stream);
                }
            }
            $disk->put($metaPath, json_encode($state));
            for ($i = 0; $i < $state['chunks']; $i++) {
                if (! $disk->exists($directory.'/'.$i.'.part')) {
                    return response()->json(['status' => 'uploading', 'chunkIndex' => $data['chunkIndex']]);
                }
            }
            $assembled = $disk->path($directory.'/assembled');
            $output = fopen($assembled, 'wb');
            try {
                for ($i = 0; $i < $state['chunks']; $i++) {
                    $input = fopen($disk->path($directory.'/'.$i.'.part'), 'rb');
                    try {
                        stream_copy_to_stream($input, $output);
                    } finally {
                        fclose($input);
                    }
                }
            } finally {
                fclose($output);
            }
            try {
                $asset = $media->videoPath($assembled, $request->user());
            } finally {
                $disk->delete($directory.'/assembled');
            }
            $state['asset'] = $asset->id;
            $disk->put($metaPath, json_encode($state));
            for ($i = 0; $i < $state['chunks']; $i++) {
                $disk->delete($directory.'/'.$i.'.part');
            }

            return $this->videoResponse($asset);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function uploadScorm(Request $request, PrivateMediaService $media)
    {
        $request->validate(['file' => 'required|file|mimes:zip|max:102400']);
        $asset = $media->scorm($request->file('file'), $request->user());

        return response()->json(['status' => 'success', 'scorm_path' => $asset->url(), 'scorm_version' => $asset->scorm_version]);
    }

    private function videoResponse(MediaAsset $asset)
    {
        return response()->json(['status' => 'success', 'video_url' => $asset->url(), 'video_provider' => 'local']);
    }
}
