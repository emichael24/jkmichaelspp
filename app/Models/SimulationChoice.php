<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimulationChoice extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'node_id',
        'next_node_id',
        'label',
        'score_impact',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'score_impact' => 'array',
            'sort_order'   => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function node()
    {
        return $this->belongsTo(SimulationNode::class, 'node_id');
    }

    public function nextNode()
    {
        return $this->belongsTo(SimulationNode::class, 'next_node_id');
    }
}
