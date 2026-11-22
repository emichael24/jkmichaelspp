<?php

namespace App\Models;

use App\Models\Traits\RecordsDomainAudit;
use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    use RecordsDomainAudit;

    protected $guarded = [];

    protected $casts = ['value' => 'json'];
}
