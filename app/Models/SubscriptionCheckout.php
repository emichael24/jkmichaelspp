<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionCheckout extends Model
{
    protected $primaryKey = 'reference';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = ['paid_at' => 'datetime', 'expires_at' => 'datetime'];
}
