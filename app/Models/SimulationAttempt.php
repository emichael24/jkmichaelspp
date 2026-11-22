<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimulationAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'simulation_id',
        'path_taken',
        'choices_made',
        'scores',
        'total_score_pct',
        'time_spent_seconds',
        'status',
        'current_node_id',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'path_taken' => 'array',
            'choices_made' => 'array',
            'scores' => 'array',
            'total_score_pct' => 'decimal:2',
            'time_spent_seconds' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function simulation()
    {
        return $this->belongsTo(Simulation::class);
    }

    public function currentNode()
    {
        return $this->belongsTo(SimulationNode::class, 'current_node_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function addToPath(int $nodeId): void
    {
        $path = $this->path_taken ?? [];
        $path[] = $nodeId;
        $this->path_taken = $path;
    }

    public function addChoice(int $nodeId, int $choiceId): void
    {
        $choices = $this->choices_made ?? [];
        $choices[] = ['node_id' => $nodeId, 'choice_id' => $choiceId];
        $this->choices_made = $choices;
    }

    public function applyScoreImpact(array $impact): void
    {
        $scores = $this->scores ?? [
            'judgment' => 50,
            'ethics' => 50,
            'stakeholder_handling' => 50,
            'risk_thinking' => 50,
        ];

        foreach ($impact as $dimension => $value) {
            if (isset($scores[$dimension])) {
                $scores[$dimension] = max(0, min(100, $scores[$dimension] + $value));
            }
        }

        $this->scores = $scores;
    }

    public function calculateTotalScore(): float
    {
        $scores = $this->scores ?? [];
        if (empty($scores)) {
            return 0;
        }

        return round(array_sum($scores) / count($scores), 2);
    }
}
