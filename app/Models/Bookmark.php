<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bookmark extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'bookmarkable_type',
        'bookmarkable_id',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bookmarkable()
    {
        return $this->morphTo();
    }
}
