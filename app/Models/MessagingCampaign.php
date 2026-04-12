<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MessagingCampaign extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'created_by', 'name', 'channel', 'type', 'subject', 'body',
        'media_url', 'status', 'audience_filter', 'recipient_count', 'sent_count',
        'delivered_count', 'opened_count', 'failed_count', 'scheduled_at',
        'started_at', 'completed_at',
    ];

    protected $casts = [
        'audience_filter' => 'array',
        'scheduled_at'    => 'datetime',
        'started_at'      => 'datetime',
        'completed_at'    => 'datetime',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logs()
    {
        return $this->hasMany(MessagingLog::class, 'campaign_id');
    }
}
