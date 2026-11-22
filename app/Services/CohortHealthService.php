<?php

namespace App\Services;

use App\Models\Cohort;
use App\Models\CohortMember;
use App\Models\CourseEnrollment;
use App\Models\DailyActivityLog;
use App\Models\ErrorPattern;
use App\Models\Intervention;
use App\Models\Lesson;
use App\Models\ReadinessScore;
use App\Models\User;
use App\Models\UserMastery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CohortHealthService
{
    /* ────────────────────────────────────────────────
     * Cohort Health Score (composite 0-100)
     * ─────────────────────────────────────────────── */

    /**
     * Calculate composite health score:
     *  - engagement_rate (30 %): % learners active in last 7 days
     *  - avg_readiness   (30 %): average readiness score across learners
     *  - on_time_rate    (25 %): % learners on-track with progress
     *  - low_intervention(15 %): inverse of intervention count
     */
    public function healthScore(Cohort $cohort): array
    {
        $learnerIds = $this->activeLearnerIds($cohort);

        if ($learnerIds->isEmpty()) {
            return [
                'score' => 0,
                'engagement_rate' => 0,
                'avg_readiness' => 0,
                'on_time_rate' => 0,
                'intervention_rate' => 0,
                'learner_count' => 0,
            ];
        }

        $engagementRate = $this->engagementRate($learnerIds);
        $avgReadiness = $this->avgReadiness($learnerIds, $cohort->course_id);
        $onTimeRate = $this->onTimeRate($cohort, $learnerIds);
        $interventionPct = $this->interventionRate($cohort, $learnerIds);

        // Lower intervention = healthier → invert
        $interventionScore = max(0, 100 - $interventionPct);

        $score = round(
            $engagementRate * 0.30 +
            $avgReadiness * 0.30 +
            $onTimeRate * 0.25 +
            $interventionScore * 0.15,
            1
        );

        return [
            'score' => $score,
            'engagement_rate' => round($engagementRate, 1),
            'avg_readiness' => round($avgReadiness, 1),
            'on_time_rate' => round($onTimeRate, 1),
            'intervention_rate' => round($interventionPct, 1),
            'learner_count' => $learnerIds->count(),
        ];
    }

    /* ────────────────────────────────────────────────
     * Mastery Heatmap: learners × domains
     * ─────────────────────────────────────────────── */

    public function masteryHeatmap(Cohort $cohort): array
    {
        $learnerIds = $this->activeLearnerIds($cohort);

        $rows = UserMastery::whereIn('user_id', $learnerIds)
            ->where('course_id', $cohort->course_id)
            ->get(['user_id', 'domain', 'accuracy_pct'])
            ->groupBy('user_id');

        $domains = UserMastery::where('course_id', $cohort->course_id)
            ->whereIn('user_id', $learnerIds)
            ->select('domain')
            ->distinct()
            ->pluck('domain')
            ->sort()
            ->values()
            ->toArray();

        $learners = $cohort->members()
            ->active()
            ->learners()
            ->with('user:id,first_name,last_name')
            ->get()
            ->map(function ($m) use ($rows, $domains) {
                $userMastery = $rows->get($m->user_id, collect());
                $cells = [];
                foreach ($domains as $domain) {
                    $entry = $userMastery->firstWhere('domain', $domain);
                    $cells[$domain] = $entry ? round((float) $entry->accuracy_pct) : null;
                }

                return [
                    'id' => $m->user_id,
                    'name' => $m->user->first_name.' '.$m->user->last_name,
                    'cells' => $cells,
                ];
            });

        return [
            'domains' => $domains,
            'learners' => $learners->values()->toArray(),
        ];
    }

    /* ────────────────────────────────────────────────
     * Engagement Heatmap: learners × days (last 4 wks)
     * ─────────────────────────────────────────────── */

    public function engagementHeatmap(Cohort $cohort): array
    {
        $learnerIds = $this->activeLearnerIds($cohort);
        $start = now()->subWeeks(4)->startOfDay();

        $logs = DailyActivityLog::whereIn('user_id', $learnerIds)
            ->where('date', '>=', $start)
            ->get(['user_id', 'date', 'time_spent_minutes'])
            ->groupBy('user_id');

        // Build date list (last 28 days)
        $dates = collect();
        for ($d = $start->copy(); $d->lte(now()); $d->addDay()) {
            $dates->push($d->toDateString());
        }

        $learners = $cohort->members()
            ->active()
            ->learners()
            ->with('user:id,first_name,last_name')
            ->get()
            ->map(function ($m) use ($logs, $dates) {
                $userLogs = $logs->get($m->user_id, collect());
                $cells = [];
                foreach ($dates as $date) {
                    $log = $userLogs->firstWhere('date', $date);
                    $cells[] = [
                        'date' => $date,
                        'active' => $log && $log->time_spent_minutes > 0,
                        'minutes' => $log ? $log->time_spent_minutes : 0,
                    ];
                }

                return [
                    'id' => $m->user_id,
                    'name' => $m->user->first_name.' '.$m->user->last_name,
                    'cells' => $cells,
                ];
            });

        return [
            'dates' => $dates->toArray(),
            'learners' => $learners->values()->toArray(),
        ];
    }

    /* ────────────────────────────────────────────────
     * Mock Score Distribution (latest mock per learner)
     * ─────────────────────────────────────────────── */

    public function mockScoreDistribution(Cohort $cohort): array
    {
        $learnerIds = $this->activeLearnerIds($cohort);

        // Get latest mock attempt per learner
        $scores = DB::table('quiz_attempts')
            ->whereIn('user_id', $learnerIds)
            ->where('quizzable_type', 'App\\Models\\MockExam')
            ->whereNotNull('score_pct')
            ->select('user_id', DB::raw('MAX(id) as latest_id'))
            ->groupBy('user_id')
            ->get()
            ->pluck('latest_id');

        $distribution = DB::table('quiz_attempts')
            ->whereIn('id', $scores)
            ->pluck('score_pct')
            ->map(fn ($s) => round((float) $s))
            ->toArray();

        // Build histogram buckets (0-9, 10-19, ..., 90-100)
        $buckets = [];
        for ($i = 0; $i < 10; $i++) {
            $lo = $i * 10;
            $hi = $i === 9 ? 100 : ($lo + 9);
            $label = "{$lo}-{$hi}%";
            $buckets[] = [
                'label' => $label,
                'count' => count(array_filter($distribution, fn ($s) => $s >= $lo && $s <= $hi)),
            ];
        }

        return [
            'buckets' => $buckets,
            'pass_line' => 70,
            'total' => count($distribution),
        ];
    }

    /* ────────────────────────────────────────────────
     * At-Risk Learner Detection
     * ─────────────────────────────────────────────── */

    public function atRiskLearners(Cohort $cohort): array
    {
        $learnerIds = $this->activeLearnerIds($cohort);
        $risks = [];

        foreach ($learnerIds as $learnerId) {
            $reasons = $this->detectRisks($learnerId, $cohort);
            if (! empty($reasons)) {
                $user = User::find($learnerId, ['id', 'first_name', 'last_name', 'last_login_at']);
                $risks[] = [
                    'id' => $learnerId,
                    'name' => $user->first_name.' '.$user->last_name,
                    'reasons' => $reasons,
                    'last_active' => $user->last_login_at?->diffForHumans() ?? 'Never',
                    'suggestion' => $this->suggestIntervention($reasons),
                ];
            }
        }

        return $risks;
    }

    /* ────────────────────────────────────────────────
     * Learner 360 Profile
     * ─────────────────────────────────────────────── */

    public function learner360(int $learnerId, Cohort $cohort): array
    {
        $user = User::findOrFail($learnerId, [
            'id', 'first_name', 'last_name', 'email', 'avatar', 'last_login_at',
        ]);

        // Progress
        $enrollment = CourseEnrollment::where('user_id', $learnerId)
            ->where('course_id', $cohort->course_id)
            ->first();

        // Mastery map
        $mastery = UserMastery::forUser($learnerId)
            ->forCourse($cohort->course_id)
            ->get(['domain', 'accuracy_pct', 'mastery_level', 'total_attempts'])
            ->toArray();

        // Readiness
        $readiness = ReadinessScore::where('user_id', $learnerId)
            ->where('course_id', $cohort->course_id)
            ->latest('created_at')
            ->first();

        // Mock history
        $mocks = DB::table('quiz_attempts')
            ->where('user_id', $learnerId)
            ->where('quizzable_type', 'App\\Models\\MockExam')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['score_pct', 'time_spent_seconds', 'created_at']);

        // Error patterns
        $errorPatterns = ErrorPattern::forUser($learnerId)
            ->forCourse($cohort->course_id)
            ->get(['pattern_type', 'frequency', 'severity']);

        // Activity (last 4 weeks)
        $activity = DailyActivityLog::forUser($learnerId)
            ->where('date', '>=', now()->subWeeks(4))
            ->orderBy('date')
            ->get(['date', 'lessons_completed', 'quizzes_completed', 'time_spent_minutes', 'xp_earned']);

        // Interventions
        $interventions = Intervention::forLearner($learnerId)
            ->forCohort($cohort->id)
            ->latest()
            ->limit(10)
            ->get(['type', 'reason', 'action_taken', 'status', 'created_at']);

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->first_name.' '.$user->last_name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'last_active' => $user->last_login_at?->diffForHumans() ?? 'Never',
            ],
            'progress' => [
                'pct' => $enrollment->progress_pct ?? 0,
                'status' => $enrollment->status ?? 'not_started',
            ],
            'mastery' => $mastery,
            'readiness' => $readiness ? [
                'score' => $readiness->score_pct,
                'confidence_band' => $readiness->confidence_band,
                'pass_likelihood' => $readiness->pass_likelihood,
                'weak_areas' => $readiness->weak_areas,
            ] : null,
            'mock_history' => $mocks->toArray(),
            'error_patterns' => $errorPatterns->toArray(),
            'activity' => $activity->toArray(),
            'interventions' => $interventions->toArray(),
        ];
    }

    /* ────────────────────────────────────────────────
     * Auto-Plan Suggestion
     * ─────────────────────────────────────────────── */

    public function suggestPlanItems(Cohort $cohort): array
    {
        $learnerIds = $this->activeLearnerIds($cohort);

        // Find weakest domains across cohort
        $weakDomains = UserMastery::whereIn('user_id', $learnerIds)
            ->where('course_id', $cohort->course_id)
            ->select('domain', DB::raw('AVG(accuracy_pct) as avg_accuracy'))
            ->groupBy('domain')
            ->orderBy('avg_accuracy')
            ->limit(3)
            ->get();

        $suggestions = [];
        foreach ($weakDomains as $wd) {
            // Find lessons that cover this domain
            $lessons = Lesson::whereHas('module', function ($q) use ($cohort) {
                $q->where('course_id', $cohort->course_id);
            })
                ->where(function ($q) use ($wd) {
                    $q->where('title', 'like', '%'.$wd->domain.'%');
                })
                ->limit(2)
                ->get(['id', 'title']);

            foreach ($lessons as $lesson) {
                $suggestions[] = [
                    'type' => 'lesson',
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'reason' => "Cohort weak in {$wd->domain} (avg ".round($wd->avg_accuracy).'%)',
                ];
            }
        }

        return $suggestions;
    }

    /* ══════════════════════════════════════════════════
     *  Private helpers
     * ═════════════════════════════════════════════════ */

    private function activeLearnerIds(Cohort $cohort): Collection
    {
        return CohortMember::where('cohort_id', $cohort->id)
            ->active()
            ->learners()
            ->pluck('user_id');
    }

    private function engagementRate(Collection $learnerIds): float
    {
        if ($learnerIds->isEmpty()) {
            return 0;
        }

        $sevenDaysAgo = now()->subDays(7);
        $activeCount = DailyActivityLog::whereIn('user_id', $learnerIds)
            ->where('date', '>=', $sevenDaysAgo)
            ->where('time_spent_minutes', '>', 0)
            ->distinct('user_id')
            ->count('user_id');

        return ($activeCount / $learnerIds->count()) * 100;
    }

    private function avgReadiness(Collection $learnerIds, int $courseId): float
    {
        if ($learnerIds->isEmpty()) {
            return 0;
        }

        return (float) ReadinessScore::whereIn('user_id', $learnerIds)
            ->where('course_id', $courseId)
            ->avg('score_pct') ?? 0;
    }

    private function onTimeRate(Cohort $cohort, Collection $learnerIds): float
    {
        if ($learnerIds->isEmpty()) {
            return 0;
        }

        $totalDays = max(1, $cohort->start_date->diffInDays($cohort->end_date));
        $elapsed = max(1, $cohort->start_date->diffInDays(now()));
        $expectedPct = min(100, ($elapsed / $totalDays) * 100);

        $onTrack = CourseEnrollment::whereIn('user_id', $learnerIds)
            ->where('course_id', $cohort->course_id)
            ->where('progress_pct', '>=', $expectedPct * 0.5) // within 50% of expected
            ->count();

        return ($onTrack / $learnerIds->count()) * 100;
    }

    private function interventionRate(Cohort $cohort, Collection $learnerIds): float
    {
        if ($learnerIds->isEmpty()) {
            return 0;
        }

        $count = Intervention::forCohort($cohort->id)->recent(30)->count();

        // Normalize: 0 interventions = 0%, 10+ = 100%
        return min(100, ($count / max(1, $learnerIds->count())) * 100);
    }

    private function detectRisks(int $learnerId, Cohort $cohort): array
    {
        $reasons = [];

        // 1. No activity in 3+ days
        $lastActivity = DailyActivityLog::forUser($learnerId)
            ->where('time_spent_minutes', '>', 0)
            ->latest('date')
            ->first();

        if (! $lastActivity || $lastActivity->date->diffInDays(now()) >= 3) {
            $days = $lastActivity ? $lastActivity->date->diffInDays(now()) : 999;
            $reasons[] = "No activity in {$days} days";
        }

        // 2. Readiness < 40%
        $readiness = ReadinessScore::where('user_id', $learnerId)
            ->where('course_id', $cohort->course_id)
            ->latest('created_at')
            ->first();

        if ($readiness && $readiness->score_pct < 40) {
            $reasons[] = 'Readiness score is '.round($readiness->score_pct).'%';
        }

        // 3. Declining trend (3 consecutive lower quiz scores)
        $recentScores = DB::table('quiz_attempts')
            ->where('user_id', $learnerId)
            ->whereNotNull('score_pct')
            ->orderByDesc('created_at')
            ->limit(3)
            ->pluck('score_pct')
            ->toArray();

        if (count($recentScores) === 3) {
            if ($recentScores[0] < $recentScores[1] && $recentScores[1] < $recentScores[2]) {
                $reasons[] = 'Declining scores (3 consecutive drops)';
            }
        }

        // 4. Behind schedule
        $enrollment = CourseEnrollment::where('user_id', $learnerId)
            ->where('course_id', $cohort->course_id)
            ->first();

        if ($enrollment) {
            $totalDays = max(1, $cohort->start_date->diffInDays($cohort->end_date));
            $elapsed = max(1, $cohort->start_date->diffInDays(now()));
            $expectedPct = min(100, ($elapsed / $totalDays) * 100);

            if ($enrollment->progress_pct < $expectedPct * 0.5) {
                $reasons[] = 'Behind schedule ('.round($enrollment->progress_pct).'% vs '.round($expectedPct).'% expected)';
            }
        }

        return $reasons;
    }

    private function suggestIntervention(array $reasons): string
    {
        $text = implode(' ', $reasons);

        if (str_contains($text, 'No activity')) {
            return 'Send a re-engagement nudge and follow up with a coaching call if no response in 48 hours.';
        }
        if (str_contains($text, 'Readiness')) {
            return 'Assign a booster plan: 3 weak-domain lessons + 1 practice quiz per day.';
        }
        if (str_contains($text, 'Declining')) {
            return 'Schedule a 1-on-1 coaching session to review study strategy.';
        }
        if (str_contains($text, 'Behind')) {
            return 'Adjust the weekly plan to focus on high-priority content only.';
        }

        return 'Review learner profile and decide on an appropriate intervention.';
    }
}
