<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmPipeline extends Model
{
    protected $fillable = ['name', 'slug', 'stages', 'is_active'];
    protected $casts = ['stages' => 'array'];
}
