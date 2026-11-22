<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushSubscription extends Model
{
    protected $fillable = ['user_id', 'endpoint_hash', 'subscription'];

    protected $hidden = ['subscription'];

    protected function casts(): array
    {
        return ['subscription' => 'encrypted:array'];
    }
}
