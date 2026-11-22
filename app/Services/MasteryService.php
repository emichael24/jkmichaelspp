<?php

namespace App\Services;

use App\Models\DailyActivityLog;
use App\Models\QuizAttempt;
use App\Models\ReadinessScore;
use App\Models\UserMastery;
use Illuminate\Support\Collection;

class MasteryService
{
    /**
     * Recalculate mastery after a completed quiz/mock attempt.
     */
    public function processAttempt(QuizAttempt $attempt): void
    {
        $responses = $attempt->responses()->with('question')->get();
        $courseId = $attempt->quizzable?->course_id;
        $userId = $attempt->user_id;

        if (! $courseId || $responses->isEmpty()) {
            return;
        }

        // Group responses by domain
        $byDomain = $responses->groupBy(fn ($r) => $r->question->domain ?? 'general');

        foreach ($byDomain as $domain => $domainResponses) {
            $this->updateDomainMastery($userId, $courseId, $domain, $domainResponses);

            // Also process subtopic-level mastery
            $bySubtopic = $domainResponses->groupBy(fn ($r) => $r->question->subtopic ?? '');
            foreach ($bySubtopic as $subtopic => $subtopicResponses) {
                if ($subtopic) {
                    $this->updateDomainMastery($userId, $courseId, $domain, $subtopicResponses, $subtopic);
                }
            }
        }

        // Recalculate readiness score
        $this->recalculateReadiness($userId, $courseId);
    }

    /**
     * Update mastery record for a specific domain (and optional subtopic).
     */
    private function updateDomainMastery(
        int $userId,
        int $courseId,
        string $domain,
        Collection $responses,
        ?string $subtopic = null
    ): void {
        $mastery = UserMastery::firstOrCreate(
            [
                'user_id' => $userId,
                'course_id' => $courseId,
                'domain' => $domain,
                'subtopic' => $subtopic ?? '',
            ],
            [
                'accuracy_pct' => 0,
                'confidence_score' => 0,
                'speed_score' => 0,
                'consistency_score' => 0,
                'improvement_rate' => 0,
                'mastery_level' => 'novice',
                'total_attempts' => 0,
            ]
        );

        $correct = $responses->where('is_correct', true)->count();
        $total = $responses->count();
        $newAccuracy = $total > 0 ? round(($correct / $total) * 100, 2) : 0;

        // Blend with previous accuracy (EMA: 70% new, 30% old for recency bias)
        $blendedAccuracy = $mastery->total_attempts > 0
            ? round(($newAccuracy * 0.7) + ($mastery->accuracy_pct * 0.3), 2)
            : $newAccuracy;

        // Confidence score: average confidence level (1-5 → 0-100)
        $avgConfidence = $responses->avg('confidence_level');
        $confidenceScore = $avgConfidence ? round(($avgConfidence / 5) * 100, 2) : 50;

        // Speed score: how fast vs expected time (higher = better)
        $speedScore = $this->calculateSpeedScore($responses);

        // Consistency score: standard deviation of recent accuracy (lower deviation = higher consistency)
        $consistencyScore = $this->calculateConsistency($userId, $courseId, $domain, $subtopic, $newAccuracy);

        // Improvement rate: compare to previous accuracy
        $improvementRate = $mastery->total_attempts > 0
            ? round($newAccuracy - $mastery->accuracy_pct, 2)
            : 0;

        $mastery->update([
            'accuracy_pct' => $blendedAccuracy,
            'confidence_score' => round(($confidenceScore * 0.7) + ($mastery->confidence_score * 0.3), 2),
            'speed_score' => round(($speedScore * 0.7) + ($mastery->speed_score * 0.3), 2),
            'consistency_score' => $consistencyScore,
            'improvement_rate' => $improvementRate,
            'total_attempts' => $mastery->total_attempts + 1,
            'last_attempt_at' => now(),
        ]);

        $mastery->recalculateLevel();
        $mastery->save();
    }

    /**
     * Speed score: ratio of expected time to actual time, capped at 100.
     */
    private function calculateSpeedScore(Collection $responses): float
    {
        $scored = $responses->filter(fn ($r) => $r->question->time_estimate_seconds > 0 && $r->time_spent_seconds > 0);

        if ($scored->isEmpty()) {
            return 50; // Default neutral
        }

        $ratios = $scored->map(function ($r) {
            $expected = $r->question->time_estimate_seconds;
            $actual = $r->time_spent_seconds;

            // If they answered in half the expected time → ratio 2.0 → score 100
            // If they used exactly expected → ratio 1.0 → score 70
            // If they used double expected → ratio 0.5 → score 35
            return min(2.0, $expected / max(1, $actual));
        });

        $avgRatio = $ratios->avg();

        return round(min(100, $avgRatio * 50), 2);
    }

    /**
     * Consistency: based on variance of recent attempt scores.
     */
    private function calculateConsistency(
        int $userId,
        int $courseId,
        string $domain,
        ?string $subtopic,
        float $currentAccuracy
    ): float {
        // Get recent quiz scores for this domain
        $recentScores = QuizAttempt::forUser($userId)
            ->completed()
            ->whereHasMorph('quizzable', ['*'], fn ($q) => $q->where('course_id', $courseId))
            ->latest()
            ->limit(10)
            ->pluck('score_pct')
            ->push($currentAccuracy);

        if ($recentScores->count() < 2) {
            return 50; // Not enough data
        }

        $mean = $recentScores->avg();
        $variance = $recentScores->map(fn ($s) => pow($s - $mean, 2))->avg();
        $stdDev = sqrt($variance);

        // Lower std dev = higher consistency score (max 100)
        // 0 std dev = 100, 25+ std dev = 0
        return round(max(0, 100 - ($stdDev * 4)), 2);
    }

    /**
     * Recalculate the overall readiness score for a course.
     */
    public function recalculateReadiness(int $userId, int $courseId): ReadinessScore
    {
        // Get all domain-level masteries (exclude subtopic rows)
        $masteries = UserMastery::forUser($userId)
            ->forCourse($courseId)
            ->where('subtopic', '')
            ->get();

        if ($masteries->isEmpty()) {
            return ReadinessScore::latestFor($userId, $courseId) ?? new ReadinessScore;
        }

        // Build domain breakdown
        $domainBreakdown = [];
        foreach ($masteries as $m) {
            $domainBreakdown[$m->domain] = [
                'accuracy' => $m->accuracy_pct,
                'composite' => $m->compositeScore(),
                'mastery_level' => $m->mastery_level,
                'total_attempts' => $m->total_attempts,
            ];
        }

        // Overall readiness is the weighted average of composite scores
        $compositeScores = $masteries->map->compositeScore();
        $overallScore = round($compositeScores->avg(), 2);

        // Weak areas: domains below 50%
        $weakAreas = $masteries->filter(fn ($m) => $m->compositeScore() < 50)
            ->pluck('domain')
            ->values()
            ->toArray();

        // Confidence band
        $confidenceBand = match (true) {
            $overallScore >= 80 => 'high',
            $overallScore >= 55 => 'medium',
            default => 'low',
        };

        // Pass likelihood
        $passLikelihood = match (true) {
            $overallScore >= 85 => 'very_likely',
            $overallScore >= 70 => 'likely',
            $overallScore >= 50 => 'possible',
            default => 'unlikely',
        };

        return ReadinessScore::create([
            'user_id' => $userId,
            'course_id' => $courseId,
            'score_pct' => $overallScore,
            'confidence_band' => $confidenceBand,
            'pass_likelihood' => $passLikelihood,
            'domain_breakdown' => $domainBreakdown,
            'weak_areas' => $weakAreas,
            'calculated_at' => now(),
            'created_at' => now(),
        ]);
    }

    /**
     * Get mastery map data for a user across all courses or a specific course.
     */
    public function getMasteryMap(int $userId, ?int $courseId = null): array
    {
        $query = UserMastery::forUser($userId)->where('subtopic', '');

        if ($courseId) {
            $query->forCourse($courseId);
        }

        $masteries = $query->get();

        return $masteries->groupBy('course_id')->map(function ($courseMasteries) {
            return $courseMasteries->map(fn ($m) => [
                'domain' => $m->domain,
                'accuracy_pct' => $m->accuracy_pct,
                'composite_score' => $m->compositeScore(),
                'mastery_level' => $m->mastery_level,
                'total_attempts' => $m->total_attempts,
                'improvement' => $m->improvement_rate,
                'trend' => $m->improvement_rate > 2 ? 'improving'
                    : ($m->improvement_rate < -2 ? 'declining' : 'stable'),
            ])->values();
        })->toArray();
    }

    /**
     * Generate a weekly report summary.
     */
    public function generateWeeklyReport(int $userId): array
    {
        $weekStart = now()->startOfWeek();
        $weekEnd = now()->endOfWeek();

        $logs = DailyActivityLog::forUser($userId)
            ->inRange($weekStart, $weekEnd)
            ->get();

        $totalHours = round($logs->sum('time_spent_minutes') / 60, 1);
        $totalQuestions = $logs->sum('questions_answered');
        $totalCorrect = $logs->sum('questions_correct');
        $accuracy = $totalQuestions > 0 ? round(($totalCorrect / $totalQuestions) * 100, 1) : 0;

        // Get improvement areas from mastery data
        $weakMasteries = UserMastery::forUser($userId)
            ->where('subtopic', '')
            ->weak()
            ->orderBy('accuracy_pct')
            ->limit(3)
            ->get(['domain', 'accuracy_pct', 'improvement_rate']);

        return [
            'period' => $weekStart->format('M d').' – '.$weekEnd->format('M d, Y'),
            'hours_studied' => $totalHours,
            'questions_answered' => $totalQuestions,
            'accuracy_pct' => $accuracy,
            'lessons_completed' => $logs->sum('lessons_completed'),
            'quizzes_completed' => $logs->sum('quizzes_completed'),
            'xp_earned' => $logs->sum('xp_earned'),
            'days_active' => $logs->count(),
            'improvement_areas' => $weakMasteries->map(fn ($m) => [
                'domain' => $m->domain,
                'accuracy' => $m->accuracy_pct,
                'trend' => $m->improvement_rate > 0 ? 'improving' : 'declining',
            ])->toArray(),
            'focus_recommendation' => $weakMasteries->first()?->domain
                ? "Focus on strengthening: {$weakMasteries->first()->domain}"
                : 'Keep up your balanced approach!',
        ];
    }
}
