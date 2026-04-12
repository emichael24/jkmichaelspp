<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LessonVideoQuiz extends Model
{
    //
    protected $fillable = [
        'lesson_id',
        'question_id',
        'timestamp_seconds',
        'require_passing',
    ];

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}
