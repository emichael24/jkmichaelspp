<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScormProgress extends Model
{
    //
    protected $fillable = [
        'user_id',
        'lesson_id',
        'cmi_core_lesson_status',
        'cmi_core_score_raw',
        'cmi_core_lesson_location',
        'suspend_data',
    ];

    protected $casts = [
        'cmi_core_score_raw' => 'integer',
        'suspend_data' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }
}
