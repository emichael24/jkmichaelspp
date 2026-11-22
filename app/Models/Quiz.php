<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Quiz extends Model
{
    protected $fillable = [
        'title',
        'course_id',
        'module_id',
        'lesson_id',
        'type',
        'question_count',
        'time_limit_minutes',
        'passing_score_pct',
        'is_randomized',
        'is_published',
        'max_attempts',
    ];

    protected function casts(): array
    {
        return [
            'question_count' => 'integer',
            'time_limit_minutes' => 'integer',
            'passing_score_pct' => 'integer',
            'is_randomized' => 'boolean',
            'is_published' => 'boolean',
            'max_attempts' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function questions()
    {
        return $this->belongsToMany(Question::class, 'quiz_questions')
            ->withPivot('sort_order')
            ->orderBy('quiz_questions.sort_order');
    }

    public function attempts()
    {
        return $this->morphMany(QuizAttempt::class, 'quizzable');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function getQuestionsForAttempt(): Collection
    {
        $questions = $this->questions()->published()->get();

        if ($this->is_randomized) {
            $questions = $questions->shuffle();
        }

        return $questions->take($this->question_count);
    }

    public function attemptsBy(User $user): int
    {
        return $this->attempts()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->count();
    }

    public function canAttempt(User $user): bool
    {
        if (! $this->max_attempts) {
            return true;
        }

        return $this->attemptsBy($user) < $this->max_attempts;
    }

    public function isTimed(): bool
    {
        return $this->time_limit_minutes !== null;
    }
}
