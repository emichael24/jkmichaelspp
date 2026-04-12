<?php

namespace App\Services;

use App\Models\Simulation;
use App\Models\SimulationAttempt;
use App\Models\SimulationChoice;
use App\Models\SimulationNode;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SimulationService
{
    /**
     * Start a new simulation attempt. Returns the first node.
     */
    public function start(Simulation $simulation, User $user): array
    {
        $startNode = $simulation->startNode;

        if (! $startNode) {
            throw new \RuntimeException('Simulation has no start node.');
        }

        $attempt = SimulationAttempt::create([
            'user_id'       => $user->id,
            'simulation_id' => $simulation->id,
            'path_taken'    => [$startNode->id],
            'choices_made'  => [],
            'scores'        => [
                'judgment'             => 50,
                'ethics'               => 50,
                'stakeholder_handling' => 50,
                'risk_thinking'        => 50,
            ],
            'status'          => 'in_progress',
            'current_node_id' => $startNode->id,
        ]);

        return [
            'attempt' => $attempt,
            'node'    => $this->formatNode($startNode),
        ];
    }

    /**
     * Resume an in-progress attempt. Returns the current node.
     */
    public function resume(SimulationAttempt $attempt): array
    {
        if ($attempt->isCompleted()) {
            throw new \RuntimeException('Attempt is already completed.');
        }

        $currentNode = $attempt->currentNode;

        return [
            'attempt' => $attempt,
            'node'    => $this->formatNode($currentNode),
        ];
    }

    /**
     * Process a choice and advance to the next node.
     */
    public function makeChoice(SimulationAttempt $attempt, int $nodeId, int $choiceId): array
    {
        if ($attempt->isCompleted()) {
            throw new \RuntimeException('Attempt is already completed.');
        }

        // Validate the choice belongs to the current node
        $choice = SimulationChoice::where('id', $choiceId)
            ->where('node_id', $nodeId)
            ->firstOrFail();

        // Validate this is the current node
        if ($attempt->current_node_id !== $nodeId) {
            throw new \RuntimeException('Choice does not belong to the current node.');
        }

        return DB::transaction(function () use ($attempt, $choice) {
            // Record the choice
            $attempt->addChoice($choice->node_id, $choice->id);

            // Apply score impact
            if ($choice->score_impact) {
                $attempt->applyScoreImpact($choice->score_impact);
            }

            // Move to next node
            $nextNode = $choice->nextNode;

            if (! $nextNode) {
                throw new \RuntimeException('Choice leads to nowhere. Simulation data may be corrupt.');
            }

            // Add to path
            $attempt->addToPath($nextNode->id);
            $attempt->current_node_id = $nextNode->id;
            $attempt->save();

            return [
                'attempt' => $attempt->fresh(),
                'node'    => $this->formatNode($nextNode),
                'scores'  => $attempt->scores,
            ];
        });
    }

    /**
     * Complete the simulation attempt and calculate final scorecard.
     */
    public function complete(SimulationAttempt $attempt, int $timeSpentSeconds): array
    {
        if ($attempt->isCompleted()) {
            throw new \RuntimeException('Attempt is already completed.');
        }

        return DB::transaction(function () use ($attempt, $timeSpentSeconds) {
            $totalScore = $attempt->calculateTotalScore();

            $attempt->update([
                'status'             => 'completed',
                'total_score_pct'    => $totalScore,
                'time_spent_seconds' => $timeSpentSeconds,
                'completed_at'       => now(),
            ]);

            $simulation = $attempt->simulation;
            $bestPracticePath = $simulation->getBestPracticePath();

            return [
                'attempt'             => $attempt->fresh(),
                'scores'              => $attempt->scores,
                'total_score_pct'     => $totalScore,
                'best_practice_path'  => $bestPracticePath,
                'your_path'           => $attempt->path_taken,
                'time_spent_seconds'  => $timeSpentSeconds,
                'path_comparison'     => $this->buildPathComparison($attempt, $bestPracticePath),
            ];
        });
    }

    /**
     * Get the review data for a completed attempt.
     */
    public function getReview(SimulationAttempt $attempt): array
    {
        $simulation = $attempt->simulation->load(['nodes.choices']);
        $bestPracticePath = $simulation->getBestPracticePath();

        return [
            'attempt'            => $attempt,
            'simulation'         => $simulation,
            'scores'             => $attempt->scores,
            'total_score_pct'    => $attempt->total_score_pct,
            'best_practice_path' => $bestPracticePath,
            'your_path'          => $attempt->path_taken,
            'path_comparison'    => $this->buildPathComparison($attempt, $bestPracticePath),
            'nodes_visited'      => $this->getNodesVisited($attempt),
        ];
    }

    /**
     * Build a side-by-side comparison of the user's path vs best practice.
     */
    private function buildPathComparison(SimulationAttempt $attempt, array $bestPracticePath): array
    {
        $yourPath = $attempt->path_taken ?? [];
        $choicesMade = collect($attempt->choices_made ?? []);

        $comparison = [];
        $maxLength = max(count($yourPath), count($bestPracticePath));

        for ($i = 0; $i < $maxLength; $i++) {
            $yourNodeId = $yourPath[$i] ?? null;
            $bestNodeId = $bestPracticePath[$i] ?? null;

            $yourNode = $yourNodeId ? SimulationNode::find($yourNodeId) : null;
            $bestNode = $bestNodeId ? SimulationNode::find($bestNodeId) : null;

            $diverged = $yourNodeId !== $bestNodeId;

            $choiceData = $choicesMade->firstWhere('node_id', $yourNodeId);

            $comparison[] = [
                'step'       => $i + 1,
                'your_node'  => $yourNode ? [
                    'id'      => $yourNode->id,
                    'type'    => $yourNode->type,
                    'content' => $yourNode->content,
                ] : null,
                'best_node'  => $bestNode ? [
                    'id'      => $bestNode->id,
                    'type'    => $bestNode->type,
                    'content' => $bestNode->content,
                ] : null,
                'diverged'    => $diverged,
                'choice_made' => $choiceData ? SimulationChoice::find($choiceData['choice_id'])?->label : null,
            ];
        }

        return $comparison;
    }

    /**
     * Get detailed info for each node visited in an attempt.
     */
    private function getNodesVisited(SimulationAttempt $attempt): array
    {
        $path = $attempt->path_taken ?? [];

        return SimulationNode::whereIn('id', $path)
            ->with('choices')
            ->get()
            ->sortBy(function ($node) use ($path) {
                return array_search($node->id, $path);
            })
            ->values()
            ->map(fn ($node) => $this->formatNode($node))
            ->toArray();
    }

    /**
     * Format a node for the frontend.
     */
    private function formatNode(SimulationNode $node): array
    {
        $node->load('choices.nextNode');

        return [
            'id'               => $node->id,
            'type'             => $node->type,
            'content'          => $node->content,
            'media_url'        => $node->media_url,
            'is_start'         => $node->is_start,
            'is_best_practice' => $node->is_best_practice,
            'is_end'           => $node->isEnd(),
            'choices'          => $node->isDecision() ? $node->choices->map(fn ($c) => [
                'id'           => $c->id,
                'label'        => $c->label,
                'sort_order'   => $c->sort_order,
            ])->toArray() : [],
        ];
    }
}
