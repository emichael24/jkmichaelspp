<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Question;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class QuestionImportService
{
    public function importCsv($filePath, $courseId): array
    {
        $file = fopen($filePath, 'r');
        abort_unless($file, 422, 'The CSV file could not be read.');
        try {
            $header = fgetcsv($file);
            if (! $header) {
                throw ValidationException::withMessages(['file' => 'The CSV file is empty.']);
            }
            $header = array_map(fn ($column) => strtolower(trim(ltrim($column, "\xEF\xBB\xBF"))), $header);
            $expected = ['domain', 'type', 'difficulty', 'stem', 'option_1', 'option_2', 'option_3', 'option_4', 'correct_options', 'explanation'];
            if (array_diff($expected, $header) || count($header) !== count(array_unique($header))) {
                throw ValidationException::withMessages(['file' => 'Use the import template with unique column names.']);
            }

            return DB::transaction(function () use ($file, $header, $courseId) {
                $course = Course::whereKey($courseId)->lockForUpdate()->firstOrFail();
                app(CurriculumRevisionService::class)->assertEditable($course);
                $success = 0;
                $errors = [];
                for ($line = 2; ($row = fgetcsv($file)) !== false; $line++) {
                    abort_if($line > 5001, 422, 'Import at most 5,000 questions at a time.');
                    if (! array_filter($row, fn ($cell) => $cell !== null && trim($cell) !== '')) {
                        continue;
                    }
                    if (count($header) !== count($row)) {
                        $errors[] = "Row {$line}: Column count does not match the header.";

                        continue;
                    }
                    try {
                        $data = array_combine($header, $row);
                        $options = [];
                        for ($i = 1; $i <= 4; $i++) {
                            if (trim($data['option_'.$i]) !== '') {
                                $options[] = ['id' => (string) $i, 'text' => $data['option_'.$i]];
                            }
                        }
                        $values = [
                            'course_id' => $course->id, 'domain' => trim($data['domain']) ?: 'General',
                            'subtopic' => $data['subtopic'] ?? null, 'type' => trim($data['type']),
                            'difficulty' => strtolower(trim($data['difficulty'])), 'stem' => $data['stem'],
                            'options' => $options, 'correct_answer_ids' => array_map('trim', explode(',', $data['correct_options'])),
                            'explanation_correct' => $data['explanation'], 'status' => 'draft', 'version' => 1,
                        ];
                        Validator::make($values, [
                            'type' => 'required|in:multiple_choice,multiple_select,true_false,scenario,situational_judgment,ordering',
                            'difficulty' => 'required|in:easy,medium,hard,expert', 'stem' => 'required|string|max:30000',
                            'domain' => 'required|string|max:255', 'subtopic' => 'nullable|string|max:255',
                        ])->validate();
                        app(QuestionDefinitionValidator::class)->validate($values);
                        Question::create($values);
                        $success++;
                    } catch (ValidationException $exception) {
                        $errors[] = "Row {$line}: ".implode(' ', $exception->validator->errors()->all());
                    }
                }

                return ['success_count' => $success, 'errors' => $errors];
            });
        } finally {
            fclose($file);
        }
    }
}
