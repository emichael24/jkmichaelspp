<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;
use Exception;

class CourseMediaController extends Controller
{
    /**
     * Handle Chunked Video Uploads
     */
    public function uploadVideo(Request $request)
    {
        $file = $request->file('file');
        $fileName = $request->input('fileName');
        $chunkIndex = $request->input('chunkIndex');
        $totalChunks = $request->input('totalChunks');

        $tempPath = 'temp_uploads/' . $fileName;

        // Append chunk to the temp file
        Storage::disk('local')->append(
            $tempPath, 
            $file->get()
        );

        if ($chunkIndex == $totalChunks - 1) {
            // Upload complete! Move to final destination
            $finalPath = 'courses/videos/' . Str::uuid() . '_' . $fileName;
            Storage::disk('public')->put($finalPath, Storage::disk('local')->get($tempPath));
            Storage::disk('local')->delete($tempPath);

            return response()->json([
                'status' => 'success',
                'video_url' => asset("storage/{$finalPath}"),
                'video_provider' => 's3', // Assume local maps to equivalent logic or S3 disk
                'message' => 'Upload complete'
            ]);
        }

        return response()->json(['status' => 'uploading', 'chunkIndex' => $chunkIndex]);
    }

    /**
     * Handle SCORM .zip Upload and Extraction
     */
    public function uploadScorm(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:zip|max:512000' // Max 500MB
        ]);

        $zipFile = $request->file('file');
        $folderName = 'scorm_' . Str::uuid();
        $extractPath = storage_path("app/public/scorm/{$folderName}");

        $zip = new ZipArchive;
        if ($zip->open($zipFile->getRealPath()) === true) {
            // Extract the contents
            $zip->extractTo($extractPath);
            $zip->close();

            // Locate entry point (story.html, index_lms.html, or index.html)
            $entryPoint = $this->findScormEntryPoint($extractPath);

            if (!$entryPoint) {
                return response()->json(['error' => 'No valid entry point (HTML file) found in the SCORM package.'], 400);
            }

            // Return relative path to be saved in Lesson model
            $scormPathUrl = asset("storage/scorm/{$folderName}/{$entryPoint}");

            return response()->json([
                'status' => 'success',
                'scorm_path' => $scormPathUrl,
                'scorm_version' => '1.2', // Often assumed, can parse imsmanifest.xml later for precision
                'message' => 'SCORM Package extracted successfully.'
            ]);
        }

        return response()->json(['error' => 'Failed to open the zip file.'], 500);
    }

    /**
     * Recursively search for common SCORM entry points.
     */
    private function findScormEntryPoint($path)
    {
        $commonNames = ['story_html5.html', 'story.html', 'index_lms.html', 'index.html', 'scormdriver.html'];
        
        // Scan the root of the extracted folder
        $files = scandir($path);
        foreach ($files as $file) {
            if (in_array(strtolower($file), $commonNames)) {
                return $file;
            }
        }

        // If nested (e.g. within a subdirectory inside the zip)
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && is_dir($path . '/' . $file)) {
                $subFiles = scandir($path . '/' . $file);
                foreach ($subFiles as $subFile) {
                    if (in_array(strtolower($subFile), $commonNames)) {
                        return $file . '/' . $subFile;
                    }
                }
            }
        }

        return null;
    }
}

