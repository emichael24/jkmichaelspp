<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessagingLog extends Model
{
    protected $fillable = [
        'campaign_id', 'user_id', 'contact_id', 'channel', 'recipient',
        'subject', 'body', 'external_message_id', 'status', 'error_message',
        'sent_at', 'delivered_at', 'opened_at',
    ];

    protected $casts = [
        'sent_at'      => 'datetime',
        'delivered_at' => 'datetime',
        'opened_at'    => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(MessagingCampaign::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function contact()
    {
        return $this->belongsTo(CrmContact::class);
    }
}
