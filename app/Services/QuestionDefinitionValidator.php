<?php

namespace App\Services;

use App\Models\Lesson;
use Illuminate\Validation\ValidationException;

class QuestionDefinitionValidator
{
    public function validate(array $data): void
    {
        $type = $data['type'];
        $options = $data['options'] ?? [];
        $ids = array_column($options, 'id');
        $answers = $data['correct_answer_ids'] ?? [];
        $fail = fn (string $message) => throw ValidationException::withMessages(['correct_answer_ids' => $message]);
        if (! $answers || count($answers) !== count(array_unique($answers))) {
            $fail('Provide distinct, nonempty correct answers.');
        }
        foreach ($answers as $answer) {
            if (! is_string($answer) || trim($answer) === '' || mb_strlen($answer) > 2000) {
                $fail('Each correct answer must contain between 1 and 2,000 characters.');
            }
        }
        if (! empty($data['linked_lesson_id']) && ! Lesson::whereKey($data['linked_lesson_id'])->whereHas('module', fn ($q) => $q->where('course_id', $data['course_id']))->exists()) {
            throw ValidationException::withMessages(['linked_lesson_id' => 'Choose a lesson from this course.']);
        }
        if ($type === 'fill_blank') {
            if ($options) {
                $fail('Fill-in-the-blank questions use accepted text answers, without options.');
            }

            return;
        }
        if ($type === 'formula' && ! $options) {
            if (count($answers) !== 1 || ! is_numeric($answers[0]) || ! is_finite((float) $answers[0])) {
                $fail('Provide one finite numeric answer.');
            }

            return;
        }
        if (count($ids) < 2 || count($ids) !== count(array_unique($ids))) {
            $fail('Provide at least two options with distinct identifiers.');
        }
        foreach ($ids as $id) {
            if (! preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $id)) {
                $fail('Option identifiers must use letters, numbers, underscores or hyphens.');
            }
        }
        if ($type === 'matching') {
            if (count($answers) !== count($ids)) {
                $fail('Match every left item once.');
            }
            $left = [];
            $right = [];
            foreach ($answers as $pair) {
                $parts = explode(':', $pair);
                if (count($parts) !== 2 || ! in_array($parts[0], $ids, true) || ! in_array($parts[1], $ids, true)) {
                    $fail('Each match must reference two available items.');
                }
                $left[] = $parts[0];
                $right[] = $parts[1];
            }
            if (count(array_unique($left)) !== count($ids) || count(array_unique($right)) !== count($ids)) {
                $fail('Use each left and right item exactly once.');
            }
            foreach ($options as $option) {
                if (trim($option['match_text'] ?? '') === '') {
                    $fail('Provide the right-hand label for every matching option.');
                }
            }

            return;
        }
        if (array_diff($answers, $ids)) {
            $fail('Correct answers must refer to available options.');
        }
        if ($type === 'ordering' && count($answers) !== count($ids)) {
            $fail('Place every option in the correct sequence.');
        }
        if ($type === 'true_false' && count($ids) !== 2) {
            $fail('True/false questions require exactly two options.');
        }
        if (! in_array($type, ['ordering', 'multiple_select'], true) && count($answers) !== 1) {
            $fail('This question type requires exactly one correct option.');
        }
    }
}
