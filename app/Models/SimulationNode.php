<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimulationNode extends Model
{
    use HasFactory;

    protected $fillable = [
        'simulation_id',
        'type',
        'content',
        'media_url',
        'is_start',
        'is_best_practice',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_start'         => 'boolean',
            'is_best_practice' => 'boolean',
            'sort_order'       => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function simulation()
    {
        return $this->belongsTo(Simulation::class);
    }

    public function choices()
    {
        return $this->hasMany(SimulationChoice::class, 'node_id')->orderBy('sort_order');
    }

    public function incomingChoices()
    {
        return $this->hasMany(SimulationChoice::class, 'next_node_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isDecision(): bool
    {
        return $this->type === 'decision';
    }

    public function isEnd(): bool
    {
        return $this->type === 'end';
    }

    public function isStory(): bool
    {
        return $this->type === 'story';
    }
}
