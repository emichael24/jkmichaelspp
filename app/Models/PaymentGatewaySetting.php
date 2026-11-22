<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentGatewaySetting extends Model
{
    protected $guarded = [];

    protected $hidden = ['credentials'];

    protected $casts = ['credentials' => 'encrypted:array', 'version' => 'integer'];
}
