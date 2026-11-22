<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\QuestionImportService;
use Illuminate\Http\Request;

class QuestionImportController extends Controller
{
    public function store(Request $request, QuestionImportService $importService)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);

        try {
            $result = $importService->importCsv(
                $request->file('csv_file')->getRealPath(),
                $request->course_id
            );

            return back()->with('success', "Successfully imported {$result['success_count']} questions.");
        } catch (\Exception $e) {
            return back()->with('error', 'Import failed: '.$e->getMessage());
        }
    }
}
