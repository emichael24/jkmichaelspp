<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\AiContentService;
use Exception;

class AiStudioController extends Controller
{
    /**
     * Trigger an AI quiz generation based on course content.
     */
    public function generateQuiz(Request $request, AiContentService $aiService)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'content_text' => 'required|string|min:10',
            'domain' => 'required|string',
            'difficulty' => 'nullable|in:easy,medium,hard,expert',
            'count' => 'nullable|integer|min:1|max:10'
        ]);

        try {
            $questions = $aiService->generateQuizFromText(
                $request->content_text,
                $request->course_id,
                $request->domain,
                $request->difficulty ?? 'medium',
                $request->count ?? 5
            );

            return response()->json([
                'status' => 'success',
                'message' => "Successfully generated {$questions->count()} questions.",
                'data' => $questions
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'AI Generation failed: ' . $e->getMessage()
            ], 500);
        }
    }
}

