<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingWebhookEvent extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = ['payload' => 'encrypted:array', 'processed_at' => 'datetime'];

    protected $hidden = ['payload'];
}
