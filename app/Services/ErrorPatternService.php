<?php

namespace App\Services;

use App\Models\ErrorPattern;
use App\Models\Question;
use App\Models\QuestionResponse;
use App\Models\QuizAttempt;
use Illuminate\Support\Collection;

class ErrorPatternService
{
    /**
     * Minimum questions before pattern detection activates.
     */
    private const MIN_SAMPLE = 20;

    /**
     * Analyse a completed attempt and detect error patterns.
     */
    public function analyseAttempt(QuizAttempt $attempt): array
    {
        $userId = $attempt->user_id;
        $courseId = $attempt->quizzable?->course_id;
        $detected = [];

        if (! $courseId) {
            return $detected;
        }

        // Get wrong responses from last 30 days (rolling window)
        $recentResponses = QuestionResponse::where('user_id', $userId)
            ->where('is_correct', false)
            ->where('created_at', '>=', now()->subDays(30))
            ->with('question')
            ->get();

        if ($recentResponses->count() < self::MIN_SAMPLE) {
            return $detected;
        }

        // Run each detector
        $detectors = [
            ErrorPattern::CARELESS_FAST       => fn () => $this->detectCarelessFast($recentResponses),
            ErrorPattern::OVERTHINKING_SLOW   => fn () => $this->detectOverthinkingSlow($recentResponses),
            ErrorPattern::CONFIDENCE_MISMATCH => fn () => $this->detectConfidenceMismatch($recentResponses),
            ErrorPattern::CONCEPT_CONFUSION   => fn () => $this->detectConceptConfusion($recentResponses),
            ErrorPattern::QUALIFIER_MISREAD   => fn () => $this->detectQualifierMisread($recentResponses),
            ErrorPattern::ELIMINATION_FAILURE => fn () => $this->detectEliminationFailure($recentResponses),
        ];

        foreach ($detectors as $type => $detector) {
            $result = $detector();

            if ($result['detected']) {
                $pattern = $this->recordPattern($userId, $courseId, $type, $result);
                $detected[] = $pattern;
            }
        }

        return $detected;
    }

    /**
     * Careless/fast: wrong answer in < 30% of expected time.
     */
    private function detectCarelessFast(Collection $responses): array
    {
        $candidates = $responses->filter(function ($r) {
            $expected = $r->question->time_estimate_seconds ?? 0;
            return $expected > 0 && $r->time_spent_seconds < ($expected * 0.3);
        });

        return [
            'detected'     => $candidates->count() >= 3,
            'count'        => $candidates->count(),
            'question_ids' => $candidates->pluck('question_id')->take(5)->toArray(),
            'domain'       => $candidates->groupBy(fn ($r) => $r->question->domain)
                ->sortByDesc(fn ($g) => $g->count())
                ->keys()
                ->first() ?? 'general',
        ];
    }

    /**
     * Overthinking/slow: wrong answer in > 200% of expected time.
     */
    private function detectOverthinkingSlow(Collection $responses): array
    {
        $candidates = $responses->filter(function ($r) {
            $expected = $r->question->time_estimate_seconds ?? 0;
            return $expected > 0 && $r->time_spent_seconds > ($expected * 2.0);
        });

        return [
            'detected'     => $candidates->count() >= 3,
            'count'        => $candidates->count(),
            'question_ids' => $candidates->pluck('question_id')->take(5)->toArray(),
            'domain'       => $candidates->groupBy(fn ($r) => $r->question->domain)
                ->sortByDesc(fn ($g) => $g->count())
                ->keys()
                ->first() ?? 'general',
        ];
    }

    /**
     * Confidence mismatch: high confidence but wrong answer.
     */
    private function detectConfidenceMismatch(Collection $responses): array
    {
        $candidates = $responses->filter(fn ($r) => $r->confidence_level === 'high');

        return [
            'detected'     => $candidates->count() >= 3,
            'count'        => $candidates->count(),
            'question_ids' => $candidates->pluck('question_id')->take(5)->toArray(),
            'domain'       => $candidates->groupBy(fn ($r) => $r->question->domain)
                ->sortByDesc(fn ($g) => $g->count())
                ->keys()
                ->first() ?? 'general',
        ];
    }

    /**
     * Concept confusion: consistently wrong in the same domain.
     */
    private function detectConceptConfusion(Collection $responses): array
    {
        $byDomain = $responses->groupBy(fn ($r) => $r->question->domain ?? 'general');

        $worstDomain = $byDomain->sortByDesc(fn ($g) => $g->count())->first();
        $worstDomainName = $byDomain->sortByDesc(fn ($g) => $g->count())->keys()->first();

        $detected = $worstDomain && $worstDomain->count() >= 5;

        return [
            'detected'     => $detected,
            'count'        => $worstDomain?->count() ?? 0,
            'question_ids' => $worstDomain?->pluck('question_id')->take(5)->toArray() ?? [],
            'domain'       => $worstDomainName ?? 'general',
        ];
    }

    /**
     * Qualifier misread: wrong on questions containing MOST, LEAST, EXCEPT, NOT, etc.
     */
    private function detectQualifierMisread(Collection $responses): array
    {
        $qualifierPattern = '/\b(MOST|LEAST|EXCEPT|NOT|BEST|WORST|ALWAYS|NEVER|FIRST|LAST|PRIMARY)\b/i';

        $candidates = $responses->filter(function ($r) use ($qualifierPattern) {
            return preg_match($qualifierPattern, $r->question->stem ?? '');
        });

        // Only flag if qualifier questions are failed at a significantly higher rate
        $qualifierErrorRate = $candidates->count();
        $totalErrors = $responses->count();
        $detected = $qualifierErrorRate >= 3 && ($totalErrors > 0 && ($qualifierErrorRate / $totalErrors) > 0.25);

        return [
            'detected'     => $detected,
            'count'        => $candidates->count(),
            'question_ids' => $candidates->pluck('question_id')->take(5)->toArray(),
            'domain'       => $candidates->groupBy(fn ($r) => $r->question->domain)
                ->sortByDesc(fn ($g) => $g->count())
                ->keys()
                ->first() ?? 'general',
        ];
    }

    /**
     * Elimination failure: wrong on scenario/case-study items where elimination should help.
     */
    private function detectEliminationFailure(Collection $responses): array
    {
        $candidates = $responses->filter(function ($r) {
            $type = $r->question->type ?? '';
            return in_array($type, ['scenario', 'case_study']);
        });

        return [
            'detected'     => $candidates->count() >= 3,
            'count'        => $candidates->count(),
            'question_ids' => $candidates->pluck('question_id')->take(5)->toArray(),
            'domain'       => $candidates->groupBy(fn ($r) => $r->question->domain)
                ->sortByDesc(fn ($g) => $g->count())
                ->keys()
                ->first() ?? 'general',
        ];
    }

    /**
     * Record or update an error pattern in the database.
     */
    private function recordPattern(int $userId, int $courseId, string $type, array $data): ErrorPattern
    {
        return ErrorPattern::updateOrCreate(
            [
                'user_id'      => $userId,
                'course_id'    => $courseId,
                'pattern_type' => $type,
                'domain'       => $data['domain'],
            ],
            [
                'occurrence_count'     => $data['count'],
                'example_question_ids' => $data['question_ids'],
                'suggested_fix'        => ErrorPattern::FIX_SUGGESTIONS[$type] ?? '',
                'detected_at'          => now(),
            ]
        );
    }

    /**
     * Get error patterns summary for a user.
     */
    public function getPatterns(int $userId, ?int $courseId = null): Collection
    {
        $query = ErrorPattern::forUser($userId)->recent(30);

        if ($courseId) {
            $query->forCourse($courseId);
        }

        return $query->orderByDesc('occurrence_count')->get();
    }

    /**
     * Generate coach insight based on active error patterns.
     */
    public function generateCoachInsight(int $userId, ?int $courseId = null): ?string
    {
        $patterns = $this->getPatterns($userId, $courseId);

        if ($patterns->isEmpty()) {
            return null;
        }

        $worst = $patterns->first();

        return match ($worst->pattern_type) {
            ErrorPattern::CARELESS_FAST       => "You're rushing through questions too quickly — {$worst->occurrence_count} careless errors detected. Slow down and re-read before answering.",
            ErrorPattern::OVERTHINKING_SLOW   => "You're spending too long on some questions and still getting them wrong. Try the elimination strategy to save time.",
            ErrorPattern::CONFIDENCE_MISMATCH => "Your biggest leak is overconfidence — you felt sure on {$worst->occurrence_count} questions but got them wrong. Review the fundamentals.",
            ErrorPattern::CONCEPT_CONFUSION   => "You're consistently struggling in {$worst->domain}. Review related lessons to distinguish between similar concepts.",
            ErrorPattern::QUALIFIER_MISREAD   => "You're misreading qualifier words like MOST, LEAST, EXCEPT. Highlight these words before picking your answer.",
            ErrorPattern::ELIMINATION_FAILURE => "On scenario-based questions, you're not eliminating wrong answers effectively. Practice the 2-pass elimination method.",
            default                           => "Focus on {$worst->domain} — it's your weakest area with {$worst->occurrence_count} errors in the last 30 days.",
        };
    }
}
