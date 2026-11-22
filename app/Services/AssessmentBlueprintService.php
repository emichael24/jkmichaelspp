<?php

namespace App\Services;

use App\Models\Question;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AssessmentBlueprintService
{
    /** Convert historical percentages to exact counts using largest remainders. */
    public function counts(?array $distribution, int $total, string $field, bool $legacy = true): array
    {
        if (empty($distribution)) {
            return [];
        }
        $weights = [];
        $percentages = false;
        foreach ($distribution as $label => $value) {
            if (is_array($value)) {
                $label = $value['domain'] ?? $value['difficulty'] ?? $label;
                $value = $value['percentage'] ?? null;
                $percentages = true;
            }
            if (! is_string($label) || trim($label) === '' || strlen($label) > 255 ||
                ! is_numeric($value) || $value < 0 || (float) $value !== (float) (int) $value ||
                array_key_exists(trim($label), $weights)) {
                throw ValidationException::withMessages([$field => 'Use unique names and whole, non-negative question counts.']);
            }
            $weights[trim($label)] = (int) $value;
        }
        if ($field === 'difficulty_distribution' && array_diff(array_keys($weights), ['easy', 'medium', 'hard', 'expert'])) {
            throw ValidationException::withMessages([$field => 'Choose easy, medium, hard or expert difficulty.']);
        }
        $sum = array_sum($weights);
        if ($legacy && $sum === 100 && $total !== 100) {
            $percentages = true;
        }
        if ((! $percentages && $sum !== $total) || ($percentages && (! $legacy || $sum !== 100))) {
            throw ValidationException::withMessages([$field => "The distribution must add up to {$total} questions."]);
        }
        if (! $percentages) {
            return $weights;
        }
        $counts = $remainders = [];
        foreach ($weights as $label => $weight) {
            $exact = $weight * $total / 100;
            $counts[$label] = (int) floor($exact);
            $remainders[$label] = $exact - $counts[$label];
        }
        arsort($remainders, SORT_NUMERIC);
        $left = $total - array_sum($counts);
        foreach (array_keys($remainders) as $label) {
            if ($left-- <= 0) {
                break;
            }
            $counts[$label]++;
        }

        return $counts;
    }

    public function select(int $courseId, int $total, ?array $domainDistribution, ?array $difficultyDistribution): Collection
    {
        abort_unless($total > 0 && $total <= 300, 422, 'Choose between 1 and 300 questions.');
        $domains = $this->counts($domainDistribution, $total, 'domain_distribution');
        $difficulties = $this->counts($difficultyDistribution, $total, 'difficulty_distribution');
        $domainLabels = $domains ? array_keys($domains) : [null];
        $difficultyLabels = $difficulties ? array_keys($difficulties) : [null];
        $buckets = Question::where('course_id', $courseId)->published()
            ->selectRaw('domain, difficulty, COUNT(*) as available')->groupBy('domain', 'difficulty')->get();

        // A capacity flow satisfies both margins at once. A greedy draw can
        // exhaust a scarce domain/difficulty intersection despite a valid bank.
        $sink = 1 + count($domainLabels) + count($difficultyLabels);
        $capacity = array_fill(0, $sink + 1, array_fill(0, $sink + 1, 0));
        foreach ($domainLabels as $i => $domain) {
            $capacity[0][$i + 1] = $domain === null ? $total : $domains[$domain];
            foreach ($difficultyLabels as $j => $difficulty) {
                $capacity[$i + 1][1 + count($domainLabels) + $j] = $buckets
                    ->filter(fn ($b) => ($domain === null || $b->domain === $domain) && ($difficulty === null || $b->difficulty === $difficulty))
                    ->sum('available');
            }
        }
        foreach ($difficultyLabels as $j => $difficulty) {
            $capacity[1 + count($domainLabels) + $j][$sink] = $difficulty === null ? $total : $difficulties[$difficulty];
        }
        $residual = $capacity;
        $flow = 0;
        while ($flow < $total) {
            $parents = array_fill(0, $sink + 1, -1);
            $parents[0] = 0;
            $queue = [0];
            for ($head = 0; $head < count($queue) && $parents[$sink] === -1; $head++) {
                $from = $queue[$head];
                foreach ($residual[$from] as $to => $remaining) {
                    if ($remaining > 0 && $parents[$to] === -1) {
                        $parents[$to] = $from;
                        $queue[] = $to;
                    }
                }
            }
            if ($parents[$sink] === -1) {
                throw ValidationException::withMessages(['blueprint' => 'The published question bank cannot meet this domain and difficulty distribution. Add questions or revise the blueprint.']);
            }
            $amount = $total - $flow;
            for ($to = $sink; $to !== 0; $to = $parents[$to]) {
                $amount = min($amount, $residual[$parents[$to]][$to]);
            }
            for ($to = $sink; $to !== 0; $to = $parents[$to]) {
                $from = $parents[$to];
                $residual[$from][$to] -= $amount;
                $residual[$to][$from] += $amount;
            }
            $flow += $amount;
        }
        $selected = collect();
        foreach ($domainLabels as $i => $domain) {
            foreach ($difficultyLabels as $j => $difficulty) {
                $to = 1 + count($domainLabels) + $j;
                $count = $capacity[$i + 1][$to] - $residual[$i + 1][$to];
                if ($count === 0) {
                    continue;
                }
                $selected = $selected->concat(Question::where('course_id', $courseId)->published()
                    ->when($domain !== null, fn ($q) => $q->where('domain', $domain))
                    ->when($difficulty !== null, fn ($q) => $q->where('difficulty', $difficulty))
                    ->inRandomOrder()->limit($count)->get());
            }
        }
        abort_unless($selected->count() === $total, 409, 'The question bank changed. Please retry.');

        return $selected->shuffle()->values();
    }
}
