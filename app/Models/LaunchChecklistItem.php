<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LaunchChecklistItem extends Model
{
    protected $fillable = [
        'gate',
        'area',
        'title',
        'status',
        'owner_name',
        'notes',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string',
        ];
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
