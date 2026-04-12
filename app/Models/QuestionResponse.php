<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionResponse extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'quiz_attempt_id',
        'question_id',
        'user_id',
        'selected_answer_ids',
        'is_correct',
        'time_spent_seconds',
        'confidence_level',
        'is_flagged',
    ];

    protected function casts(): array
    {
        return [
            'selected_answer_ids' => 'array',
            'is_correct'          => 'boolean',
            'time_spent_seconds'  => 'integer',
            'is_flagged'          => 'boolean',
            'created_at'          => 'datetime',
        ];
    }

    public function attempt()
    {
        return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id');
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
