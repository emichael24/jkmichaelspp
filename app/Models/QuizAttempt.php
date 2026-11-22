<?php

namespace App\Models;

use App\Models\Traits\RecordsDomainAudit;
use Illuminate\Database\Eloquent\Model;

class QuizAttempt extends Model
{
    use RecordsDomainAudit;

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
        'question_snapshot', 'paused_at', 'remaining_seconds', 'learning_processed_at',
        'draft_responses',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'score_pct' => 'decimal:2',
            'correct_count' => 'integer',
            'total_count' => 'integer',
            'time_spent_seconds' => 'integer',
            'domain_scores' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'paused_at' => 'datetime', 'remaining_seconds' => 'integer', 'learning_processed_at' => 'datetime',
            'question_snapshot' => 'array',
            'draft_responses' => 'array',
            'expires_at' => 'datetime',
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
            $this->quizzable instanceof Quiz => $this->quizzable->passing_score_pct,
            $this->quizzable instanceof MockExam => $this->quizzable->passing_score_pct,
            default => 70,
        };

        return $this->score_pct >= $passingScore;
    }

    public function calculateScore(): void
    {
        // Eloquent's only() matches model primary keys, not keyBy() keys.
        $responses = $this->responses()->get()->toBase()->keyBy('question_id');
        $questions = collect($this->question_snapshot ?? []);
        $counted = $questions->isNotEmpty() ? $responses->only($questions->pluck('id')->all()) : $responses;
        $correct = min($this->total_count, $counted->where('is_correct', true)->count());
        $domains = [];
        foreach ($questions->groupBy('domain') as $domain => $items) {
            $domainResponses = $counted->only($items->pluck('id')->all());
            $right = $domainResponses->where('is_correct', true)->count();
            $points = $domainResponses->sum(fn ($r) => $r->score_fraction ?? ($r->is_correct ? 1 : 0));
            $domains[$domain] = ['correct' => $right, 'points' => round($points, 4), 'total' => $items->count(), 'pct' => round($points / $items->count() * 100, 1)];
        }
        $this->update([
            'correct_count' => $correct,
            'score_pct' => $this->total_count ? round($counted->sum(fn ($r) => $r->score_fraction ?? ($r->is_correct ? 1 : 0)) / $this->total_count * 100, 2) : 0,
            'time_spent_seconds' => $counted->sum('time_spent_seconds'),
            'domain_scores' => $domains,
            'status' => 'completed', 'completed_at' => now(),
        ]);
        foreach ($questions->pluck('id') as $id) {
            $question = Question::withTrashed()->find($id);
            if (! $question) {
                continue;
            }
            $responses = $question->responses()->whereHas('attempt', fn ($q) => $q->where('status', 'completed'));
            $question->update(['correct_rate' => (clone $responses)->avg('is_correct') ?? 0, 'avg_time_seconds' => (clone $responses)->avg('time_spent_seconds') ?? 0]);
        }

    }

    public function getReview(): array
    {
        return $this->responses()
            ->with('question')
            ->get()
            ->map(function (QuestionResponse $response) {
                $saved = collect($this->question_snapshot ?? [])->firstWhere('id', $response->question_id);
                if ($saved) {
                    $response->setRelation('question', (new Question)->forceFill($saved));
                }

                return $response;
            })
            ->map(fn (QuestionResponse $r) => [
                'question_id' => $r->question_id,
                'stem' => $r->question->stem,
                'type' => $r->question->type,
                'options' => $r->question->options,
                'is_correct' => $r->is_correct,
                'score_fraction' => $r->score_fraction ?? ($r->is_correct ? 1 : 0),
                'grading_config' => $r->question->grading_config,
                'correct_answer_ids' => $r->question->correct_answer_ids,
                'your_answer_ids' => $r->selected_answer_ids,
                'explanation_correct' => $r->question->explanation_correct,
                'explanation_incorrect' => $r->question->explanation_incorrect,
                'exam_tip' => $r->question->exam_tip,
                'linked_lesson_id' => $r->question->linked_lesson_id,
                'confidence_level' => $r->confidence_level,
                'time_spent_seconds' => $r->time_spent_seconds,
                'is_flagged' => $r->is_flagged,
                'domain' => $r->question->domain,
                'subtopic' => $r->question->subtopic,
                'difficulty' => $r->question->difficulty,
            ])
            ->toArray();
    }
}
