<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappSession extends Model
{
    protected $fillable = ['session_id', 'name', 'phone', 'status', 'webhook_url', 'connected_at'];

    protected $casts = ['connected_at' => 'datetime'];
}
