<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ScormProgress;
use Illuminate\Support\Facades\Auth;

class ScormController extends Controller
{
    /**
     * Handle SCORM run-time tracking cmi.* commits from the iframe player.
     */
    public function commit(Request $request, $lesson_id)
    {
        $request->validate([
            'cmi_core_lesson_status' => 'required|string',
            'cmi_core_score_raw' => 'nullable|integer',
            'cmi_core_lesson_location' => 'nullable|string',
            'suspend_data' => 'nullable|array',
        ]);

        $progress = ScormProgress::updateOrCreate(
            ['user_id' => Auth::id(), 'lesson_id' => $lesson_id],
            $request->only([
                'cmi_core_lesson_status',
                'cmi_core_score_raw',
                'cmi_core_lesson_location',
                'suspend_data'
            ])
        );

        return response()->json([
            'status' => 'success',
            'message' => 'SCORM progress saved successfully',
            'data' => $progress
        ]);
    }
}

