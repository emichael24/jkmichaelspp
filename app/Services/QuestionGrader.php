<?php

namespace App\Services;

use App\Models\Question;

class QuestionGrader
{
    public function score(Question $question, array $answers): float
    {
        $expected = array_map('strval', $question->correct_answer_ids ?? []);
        $actual = array_map('strval', array_values($answers));
        if (! $expected || ! $actual || count($actual) !== count(array_unique($actual))) {
            return 0.0;
        }
        $config = $question->grading_config ?? [];
        if ($question->type === 'fill_blank') {
            $normalize = fn ($value) => ($config['case_sensitive'] ?? false) ? trim(preg_replace('/\s+/u', ' ', $value)) : mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value)));

            return count($actual) === 1 && in_array($normalize($actual[0]), array_map($normalize, $expected), true) ? 1.0 : 0.0;
        }
        if ($question->type === 'formula' && empty($question->options)) {
            if (count($actual) !== 1 || ! is_numeric($actual[0]) || ! is_numeric($expected[0])) {
                return 0.0;
            }
            $answer = (float) $actual[0];
            $key = (float) $expected[0];

            return is_finite($answer) && abs($answer - $key) <= max(0, (float) ($config['tolerance'] ?? 0.000001)) ? 1.0 : 0.0;
        }
        if ($question->type === 'ordering') {
            if ($actual === $expected) {
                return 1.0;
            }
            if (! ($config['partial_credit'] ?? false) || count($actual) !== count($expected) || array_diff($actual, $expected)) {
                return 0.0;
            }
            $right = 0;
            foreach ($expected as $i => $id) {
                $right += ($actual[$i] ?? null) === $id ? 1 : 0;
            }

            return $right / count($expected);
        }
        if ($question->type === 'matching') {
            $left = array_map(fn ($pair) => explode(':', $pair, 2)[0], $actual);
            if (count(array_unique($left)) !== count($left)) {
                return 0.0;
            }
        }
        sort($expected);
        sort($actual);
        if ($expected === $actual) {
            return 1.0;
        }
        if (($config['partial_credit'] ?? false) && in_array($question->type, ['multiple_select', 'matching'])) {
            return max(0, (count(array_intersect($expected, $actual)) - count(array_diff($actual, $expected))) / count($expected));
        }

        return 0.0;
    }
}
