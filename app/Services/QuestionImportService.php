<?php

namespace App\Services;

use App\Models\Question;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Exception;

class QuestionImportService
{
    /**
     * Parse and import a CSV file of questions.
     */
    public function importCsv($filePath, $courseId)
    {
        $file = fopen($filePath, 'r');
        $header = fgetcsv($file); // Get the header row

        // Sanitize header to lowercase and trim spaces
        $header = array_map(function($col) { return strtolower(trim($col)); }, $header);

        $expectedColumns = ['domain', 'type', 'difficulty', 'stem', 'option_1', 'option_2', 'option_3', 'option_4', 'correct_options', 'explanation'];
        
        $missing = array_diff($expectedColumns, $header);
        if (!empty($missing)) {
            throw new Exception("Invalid CSV format. Missing columns: " . implode(', ', $missing));
        }

        $successCount = 0;
        $errors = [];
        $rowNum = 2; // starting after header

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($file)) !== false) {
                // Skip empty rows
                if (count(array_filter($row)) == 0) continue;

                $data = array_combine($header, $row);

                try {
                    $this->createQuestion($data, $courseId);
                    $successCount++;
                } catch (Exception $e) {
                    $errors[] = "Row {$rowNum}: " . $e->getMessage();
                }
                $rowNum++;
            }
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            fclose($file);
            throw $e;
        }

        fclose($file);

        return [
            'success_count' => $successCount,
            'errors' => $errors
        ];
    }

    private function createQuestion($data, $courseId)
    {
        // Build Options Array
        $options = [];
        for ($i = 1; $i <= 4; $i++) {
            $optKey = "option_{$i}";
            if (!empty($data[$optKey])) {
                $options[] = [
                    'id' => $i,
                    'text' => $data[$optKey]
                ];
            }
        }

        // Parse correct options (e.g., "1" or "1, 3")
        $correctAnswers = array_map('intval', explode(',', $data['correct_options']));

        if (count($options) < 2) {
            throw new Exception("A question must have at least 2 options.");
        }

        Question::create([
            'uuid' => (string) Str::uuid(),
            'course_id' => $courseId,
            'domain' => $data['domain'] ?? 'General',
            'subtopic' => $data['subtopic'] ?? null,
            'type' => $data['type'] ?? 'multiple_choice',
            'difficulty' => strtolower($data['difficulty'] ?? 'medium'),
            'stem' => $data['stem'],
            'options' => json_encode($options),
            'correct_answer_ids' => json_encode($correctAnswers),
            'explanation_correct' => $data['explanation'] ?? 'No explanation provided.',
            'status' => 'published',
            'version' => 1
        ]);
    }
}
