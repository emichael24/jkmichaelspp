<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LessonProgress extends Model
{
    protected $table = 'lesson_progress';

    protected $fillable = [
        'user_id',
        'lesson_id',
        'status',
        'progress_pct',
        'video_position_sec',
        'time_spent_seconds',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'progress_pct'      => 'integer',
            'video_position_sec' => 'integer',
            'time_spent_seconds' => 'integer',
            'completed_at'       => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }
}
