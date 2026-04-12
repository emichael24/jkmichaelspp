<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MockExam extends Model
{
    protected $fillable = [
        'course_id',
        'title',
        'description',
        'type',
        'question_count',
        'time_limit_minutes',
        'passing_score_pct',
        'domain_distribution',
        'difficulty_distribution',
        'is_published',
        'is_exam_simulation',
        'allow_pause',
    ];

    protected function casts(): array
    {
        return [
            'question_count'          => 'integer',
            'time_limit_minutes'      => 'integer',
            'passing_score_pct'       => 'integer',
            'domain_distribution'     => 'array',
            'difficulty_distribution' => 'array',
            'is_published'            => 'boolean',
            'is_exam_simulation'      => 'boolean',
            'allow_pause'             => 'boolean',
        ];
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function attempts()
    {
        return $this->morphMany(QuizAttempt::class, 'quizzable');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }
}
