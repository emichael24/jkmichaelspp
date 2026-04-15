<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizAttempt extends Model
{
    protected $fillable = [
        'user_id',
        'quizzable_type',
        'quizzable_id',
        'status',
        'score_pct',
        'correct_count',
        'total_count',
        'time_spent_seconds',
        'domain_scores',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'score_pct'          => 'decimal:2',
            'correct_count'      => 'integer',
            'total_count'        => 'integer',
            'time_spent_seconds' => 'integer',
            'domain_scores'      => 'array',
            'started_at'         => 'datetime',
            'completed_at'       => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function quizzable()
    {
        return $this->morphTo();
    }

    public function responses()
    {
        return $this->hasMany(QuestionResponse::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isPassed(): bool
    {
        $passingScore = match (true) {
            $this->quizzable instanceof Quiz     => $this->quizzable->passing_score_pct,
            $this->quizzable instanceof MockExam => $this->quizzable->passing_score_pct,
            default                              => 70,
        };

        return $this->score_pct >= $passingScore;
    }

    public function calculateScore(): void
    {
        $responses = $this->responses;

        $correctCount = $responses->where('is_correct', true)->count();
        $totalCount = $responses->count();
        $scorePct = $totalCount > 0 ? round(($correctCount / $totalCount) * 100, 2) : 0;
        $totalTime = $responses->sum('time_spent_seconds');

        // Calculate domain scores
        $domainScores = [];
        $responsesByDomain = $responses->load('question')->groupBy(fn ($r) => $r->question->domain);

        foreach ($responsesByDomain as $domain => $domainResponses) {
            $domainCorrect = $domainResponses->where('is_correct', true)->count();
            $domainTotal = $domainResponses->count();
            $domainScores[$domain] = [
                'correct' => $domainCorrect,
                'total'   => $domainTotal,
                'pct'     => $domainTotal > 0 ? round(($domainCorrect / $domainTotal) * 100, 1) : 0,
            ];
        }

        $this->update([
            'correct_count'      => $correctCount,
            'score_pct'          => $scorePct,
            'time_spent_seconds' => $totalTime,
            'domain_scores'      => $domainScores,
            'status'             => 'completed',
            'completed_at'       => now(),
        ]);
    }

    public function getReview(): array
    {
        return $this->responses()
            ->with('question')
            ->get()
            ->map(fn (QuestionResponse $r) => [
                'question_id'          => $r->question_id,
                'stem'                 => $r->question->stem,
                'type'                 => $r->question->type,
                'options'              => $r->question->options,
                'is_correct'           => $r->is_correct,
                'correct_answer_ids'   => $r->question->correct_answer_ids,
                'your_answer_ids'      => $r->selected_answer_ids,
                'explanation_correct'  => $r->question->explanation_correct,
                'explanation_incorrect' => $r->question->explanation_incorrect,
                'exam_tip'             => $r->question->exam_tip,
                'linked_lesson_id'     => $r->question->linked_lesson_id,
                'confidence_level'     => $r->confidence_level,
                'time_spent_seconds'   => $r->time_spent_seconds,
                'is_flagged'           => $r->is_flagged,
                'domain'               => $r->question->domain,
                'subtopic'             => $r->question->subtopic,
                'difficulty'           => $r->question->difficulty,
            ])
            ->toArray();
    }
}
