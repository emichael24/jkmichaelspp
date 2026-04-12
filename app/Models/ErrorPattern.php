<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErrorPattern extends Model
{
    protected $fillable = [
        'user_id',
        'course_id',
        'pattern_type',
        'domain',
        'occurrence_count',
        'example_question_ids',
        'suggested_fix',
        'detected_at',
    ];

    protected $casts = [
        'example_question_ids' => 'array',
        'occurrence_count'     => 'integer',
        'detected_at'          => 'datetime',
    ];

    // Pattern type constants
    public const CONCEPT_CONFUSION    = 'concept_confusion';
    public const CARELESS_FAST        = 'careless_fast';
    public const OVERTHINKING_SLOW    = 'overthinking_slow';
    public const CONFIDENCE_MISMATCH  = 'confidence_mismatch';
    public const QUALIFIER_MISREAD    = 'qualifier_misread';
    public const ELIMINATION_FAILURE  = 'elimination_failure';

    public const FIX_SUGGESTIONS = [
        self::CONCEPT_CONFUSION   => 'Review the related lessons to distinguish between similar concepts.',
        self::CARELESS_FAST       => 'Slow down and re-read each question carefully before answering.',
        self::OVERTHINKING_SLOW   => 'Try the 30-second elimination strategy — remove obviously wrong answers first.',
        self::CONFIDENCE_MISMATCH => 'You may be confusing similar concepts. Review the underlying fundamentals.',
        self::QUALIFIER_MISREAD   => 'Highlight qualifiers like MOST, LEAST, EXCEPT, NOT before answering.',
        self::ELIMINATION_FAILURE => 'Practice the 2-pass elimination method on scenario-based items.',
    ];

    // ── Relationships ────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    // ── Scopes ───────────────────────────────────

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForCourse($query, int $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('detected_at', '>=', now()->subDays($days));
    }
}
