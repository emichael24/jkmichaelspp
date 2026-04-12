<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmActivity extends Model
{
    protected $fillable = [
        'contact_id', 'deal_id', 'user_id', 'type', 'subject',
        'description', 'direction', 'outcome', 'due_at', 'completed_at',
    ];

    protected $casts = [
        'due_at'       => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function contact()
    {
        return $this->belongsTo(CrmContact::class, 'contact_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function deal()
    {
        return $this->belongsTo(CrmDeal::class, 'deal_id');
    }
}
