<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiGeneratedQuiz extends Model
{
    protected $fillable = [
        'lesson_id', 'course_id', 'created_by', 'title', 'questions', 'question_count', 'is_promoted',
    ];

    protected $casts = ['questions' => 'array'];

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
