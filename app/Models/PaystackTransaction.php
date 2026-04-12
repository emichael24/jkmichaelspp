<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaystackTransaction extends Model
{
    protected $fillable = [
        'order_id', 'user_id', 'reference', 'access_code', 'authorization_url',
        'amount', 'currency', 'status', 'metadata', 'gateway_response',
        'channel', 'ip_address', 'paid_at',
    ];

    protected $casts = [
        'metadata'         => 'array',
        'gateway_response' => 'array',
        'paid_at'          => 'datetime',
        'amount'           => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
