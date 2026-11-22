<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ExternalIntegrationException;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\AiContentService;
use App\Services\CurriculumRevisionService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AiStudioController extends Controller
{
    /**
     * Trigger an AI quiz generation based on course content.
     */
    public function generateQuiz(Request $request, AiContentService $aiService)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'content_text' => 'required|string|min:10|max:7000',
            'domain' => 'required|string|max:255',
            'difficulty' => 'nullable|in:easy,medium,hard,expert',
            'count' => 'nullable|integer|min:1|max:10',
        ]);

        $course = Course::findOrFail($request->course_id);
        abort_unless($request->user()->hasRole('platform_admin') || $course->author_id === $request->user()->id, 403);
        app(CurriculumRevisionService::class)->assertEditable($course);

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
                'data' => $questions,
            ]);
        } catch (ExternalIntegrationException|ValidationException $e) {
            throw $e;
        } catch (Exception $e) {
            report($e);

            return response()->json([
                'status' => 'error',
                'message' => 'Content generation could not finish. Your existing course content has not changed. Please retry or contact support.',
            ], 500);
        }
    }
}
