<?php

namespace App\Models;

use App\Models\Traits\RecordsDomainAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentSubmission extends Model
{
    use RecordsDomainAudit;

    protected $fillable = [
        'assignment_id',
        'user_id',
        'submission_number',
        'content',
        'answers',
        'file_urls',
        'status',
        'score',
        'rubric_scores',
        'feedback',
        'feedback_audio_url',
        'graded_by',
        'graded_at',
        'submitted_at',
    ];

    protected $casts = [
        'file_urls' => 'array',
        'answers' => 'array',
        'rubric_scores' => 'array',
        'graded_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    /* ── Relationships ─────────────────────────────── */

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    /* ── Scopes ────────────────────────────────────── */

    public function scopePending($query)
    {
        return $query->whereIn('status', ['submitted', 'resubmitted']);
    }

    public function scopeGraded($query)
    {
        return $query->where('status', 'graded');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /* ── Helpers ───────────────────────────────────── */

    public function isGraded(): bool
    {
        return $this->status === 'graded';
    }

    public function scorePct(): ?float
    {
        if (is_null($this->score)) {
            return null;
        }

        $max = $this->assignment?->max_score ?: 100;

        return round(($this->score / $max) * 100, 1);
    }
}
