<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmDealStageHistory extends Model
{
    protected $table = 'crm_deal_stage_history';

    public $timestamps = false; // We use moved_at

    protected $guarded = [];

    protected $casts = [
        'moved_at' => 'datetime',
    ];

    public function deal()
    {
        return $this->belongsTo(CrmDeal::class, 'deal_id');
    }

    public function movedBy()
    {
        return $this->belongsTo(User::class, 'moved_by_user_id');
    }
}
