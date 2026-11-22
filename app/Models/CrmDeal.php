<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmDeal extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'contact_id', 'pipeline_id', 'organization_id', 'title', 'value', 'weighted_value', 'currency',
        'stage', 'status', 'notes', 'expected_close_date', 'closed_date', 'assigned_to',
        'probability', 'lost_reason', 'win_reason', 'competitors',
    ];

    protected $casts = [
        'expected_close_date' => 'date',
        'closed_date' => 'date',
        'value' => 'decimal:2',
    ];

    public function contact()
    {
        return $this->belongsTo(CrmContact::class, 'contact_id');
    }

    public function organization()
    {
        return $this->belongsTo(CrmOrganization::class, 'organization_id');
    }

    public function stageHistory()
    {
        return $this->hasMany(CrmDealStageHistory::class, 'deal_id');
    }

    public function scopeWon($query)
    {
        return $query->where('status', 'won');
    }

    public function scopeLost($query)
    {
        return $query->where('status', 'lost');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function pipeline()
    {
        return $this->belongsTo(CrmPipeline::class, 'pipeline_id');
    }
}
