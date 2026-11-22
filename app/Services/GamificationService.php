<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\DailyActivityLog;
use App\Models\Mission;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Models\UserGamification;
use App\Models\UserMastery;
use App\Models\UserMission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GamificationService
{
    // ── XP Values per Action ─────────────────────

    public const XP_VALUES = [
        'lesson_complete' => 10,
        'quiz_pass' => 15,
        'quiz_excellent' => 25, // 90%+
        'mock_complete' => 50,
        'simulation_complete' => 30,
        'daily_login' => 5,
        'streak_7_day_bonus' => 50,
        'mission_complete' => 0,  // varies per mission
        'internship_task' => 20,
        'flashcard_review' => 10,
    ];

    /**
     * Award XP for an action and handle all side-effects:
     * - XP increment
     * - Level-up check
     * - Streak update
     * - Badge checks
     * - Mission progress
     * - Daily log update
     *
     * Returns an events array for celebration UI.
     */
    public function awardXp(int $userId, string $action, array $context = []): array
    {
        $source = $action === 'daily_login' ? now()->toDateString() : ($context['event_key'] ?? null);
        if (! $source || ! array_key_exists($action, self::XP_VALUES)) {
            throw new \InvalidArgumentException('Rewards require a known action and a stable source event.');
        }

        return DB::transaction(function () use ($userId, $action, $context, $source) {
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $key = hash('sha256', $userId.'|'.$action.'|'.$source);
            if (DB::table('gamification_events')->where('event_key', $key)->exists()) {
                return [];
            }
            $before = UserGamification::forUser($userId)->total_xp;
            $events = $this->applyReward($userId, $action, $context);
            DB::table('gamification_events')->insert(['user_id' => $userId, 'event_key' => $key,
                'action' => $action, 'xp' => UserGamification::forUser($userId)->total_xp - $before, 'created_at' => now()]);

            return $events;
        }, 3);
    }

    private function applyReward(int $userId, string $action, array $context): array
    {
        $events = [];
        $xp = $context['xp'] ?? (self::XP_VALUES[$action] ?? 0);

        $gam = UserGamification::forUser($userId);

        // ── Award XP ─────────────────────────────────
        $gam->total_xp += $xp;

        // ── Level-up check ───────────────────────────
        $nextLevelXp = DB::table('xp_levels')
            ->where('level', $gam->level + 1)
            ->value('xp_required');

        $levelledUp = false;
        while ($nextLevelXp !== null && $gam->total_xp >= $nextLevelXp) {
            $gam->level++;
            $levelledUp = true;
            $nextLevelXp = DB::table('xp_levels')
                ->where('level', $gam->level + 1)
                ->value('xp_required');
        }

        if ($levelledUp) {
            $events[] = [
                'type' => 'level_up',
                'level' => $gam->level,
                'title' => $gam->levelTitle(),
            ];
        }

        // ── Streak ───────────────────────────────────
        $streakResult = $gam->updateStreak();
        if ($streakResult['streak_continued'] && $gam->current_streak_days % 7 === 0) {
            $events[] = [
                'type' => 'streak_milestone',
                'streak' => $gam->current_streak_days,
            ];
            // Award 7-day streak bonus XP
            $gam->total_xp += self::XP_VALUES['streak_7_day_bonus'];
            $xp += self::XP_VALUES['streak_7_day_bonus'];
        }

        $gam->save();

        // ── Daily activity log ───────────────────────
        $log = DailyActivityLog::today($userId);
        $log->incrementCounter('xp_earned', $xp);

        // Map action to daily log fields
        match ($action) {
            'lesson_complete' => $log->incrementCounter('lessons_completed'),
            'quiz_pass', 'quiz_excellent' => $log->incrementCounter('quizzes_completed'),
            'flashcard_review' => $log->incrementCounter('flashcards_reviewed'),
            'simulation_complete' => $log->incrementCounter('simulations_completed'),
            'daily_login' => null,
            default => null,
        };

        if (isset($context['questions_answered'])) {
            $log->incrementCounter('questions_answered', $context['questions_answered']);
        }
        if (isset($context['questions_correct'])) {
            $log->incrementCounter('questions_correct', $context['questions_correct']);
        }
        if (isset($context['time_minutes'])) {
            $log->incrementCounter('time_spent_minutes', $context['time_minutes']);
        }

        // ── Badge checks ─────────────────────────────
        $badgeEvents = $this->checkBadges($userId, $action, $context);
        $events = array_merge($events, $badgeEvents);

        // ── Mission progress ─────────────────────────
        $missionEvents = $this->advanceMissions($userId, $action, $context);
        $events = array_merge($events, $missionEvents);

        return $events;
    }

    /**
     * Check and award badges based on the action.
     */
    private function checkBadges(int $userId, string $action, array $context): array
    {
        $events = [];
        $badgesToCheck = $this->getBadgesForAction($action);

        foreach ($badgesToCheck as $badge) {
            if ($badge->isEarnedBy($userId)) {
                continue; // Already earned
            }

            if ($this->meetsBadgeCriteria($userId, $badge, $context)) {
                // Award the badge
                DB::table('user_badges')->insert([
                    'user_id' => $userId,
                    'badge_id' => $badge->id,
                    'earned_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Award badge XP
                if ($badge->xp_reward > 0) {
                    $gam = UserGamification::forUser($userId);
                    $gam->total_xp += $badge->xp_reward;
                    $gam->save();
                }

                $events[] = [
                    'type' => 'badge_earned',
                    'badge_id' => $badge->id,
                    'badge_name' => $badge->name,
                    'badge_icon' => $badge->icon,
                    'xp_reward' => $badge->xp_reward,
                ];
            }
        }

        return $events;
    }

    /**
     * Get badges relevant to the action.
     */
    private function getBadgesForAction(string $action): Collection
    {
        $categoryMap = [
            'lesson_complete' => 'learning',
            'quiz_pass' => 'completion',
            'quiz_excellent' => ['mastery', 'completion'],
            'mock_complete' => 'completion',
            'simulation_complete' => 'completion',
            'daily_login' => 'streak',
            'flashcard_review' => 'learning',
            'internship_task' => 'internship',
        ];

        $categories = $categoryMap[$action] ?? null;

        if (! $categories) {
            return collect();
        }

        $categories = (array) $categories;

        return Badge::active()->whereIn('category', $categories)->get();
    }

    /**
     * Check if a user meets the criteria for a badge.
     */
    private function meetsBadgeCriteria(int $userId, Badge $badge, array $context): bool
    {
        $criteria = $badge->criteria ?? [];
        $type = $criteria['type'] ?? '';

        return match ($type) {
            'lessons_count' => DailyActivityLog::forUser($userId)->get()->sum('lessons_completed') >= ($criteria['count'] ?? PHP_INT_MAX),

            'streak_days' => (UserGamification::forUser($userId)->current_streak_days ?? 0) >= ($criteria['count'] ?? PHP_INT_MAX),

            'domain_mastery' => UserMastery::forUser($userId)
                ->where('subtopic', '')
                ->where('accuracy_pct', '>=', ($criteria['min_accuracy'] ?? 90))
                ->exists(),

            'all_domains_mastery' => $this->checkAllDomainsMastery($userId, $criteria['min_accuracy'] ?? 70),

            'quizzes_passed' => QuizAttempt::forUser($userId)
                ->completed()
                ->where('score_pct', '>=', 70)
                ->count() >= ($criteria['count'] ?? PHP_INT_MAX),

            'mock_score' => isset($context['score_pct']) && $context['score_pct'] >= ($criteria['min_score'] ?? 90),

            'course_complete' => isset($context['course_completed']) && $context['course_completed'],

            'internship_submissions' => ($context['total_submissions'] ?? 0) >= ($criteria['count'] ?? PHP_INT_MAX),

            default => false,
        };
    }

    /**
     * Check if user has mastery in all domains for any course.
     */
    private function checkAllDomainsMastery(int $userId, float $minAccuracy): bool
    {
        $courses = UserMastery::forUser($userId)
            ->where('subtopic', '')
            ->select('course_id')
            ->distinct()
            ->pluck('course_id');

        foreach ($courses as $courseId) {
            $allDomains = UserMastery::forUser($userId)
                ->forCourse($courseId)
                ->where('subtopic', '')
                ->get();

            if ($allDomains->isNotEmpty() && $allDomains->every(fn ($m) => $m->accuracy_pct >= $minAccuracy)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Advance missions based on user action.
     */
    private function advanceMissions(int $userId, string $action, array $context): array
    {
        $events = [];

        // Map actions to mission criteria keys
        $criteriaKeyMap = [
            'lesson_complete' => 'complete_lessons',
            'quiz_pass' => 'pass_quiz',
            'quiz_excellent' => 'pass_quiz_high',
            'mock_complete' => 'complete_mock',
            'flashcard_review' => 'review_flashcards',
            'daily_login' => 'daily_login',
            'simulation_complete' => 'complete_simulation',
        ];

        $criteriaKey = $criteriaKeyMap[$action] ?? null;
        if (! $criteriaKey) {
            return $events;
        }

        // Find active missions matching this criteria
        $userMissions = UserMission::forUser($userId)
            ->active()
            ->with('mission')
            ->get();

        foreach ($userMissions as $um) {
            $missionAction = $um->mission->criteria['action'] ?? '';

            if ($missionAction === $criteriaKey) {
                // Check extra criteria (e.g., min_score for high_scorer)
                if ($this->meetsExtraMissionCriteria($um->mission, $context)) {
                    $justCompleted = $um->advance();

                    if ($justCompleted) {
                        // Award mission XP
                        $missionXp = $um->mission->xp_reward;
                        if ($missionXp > 0) {
                            $gam = UserGamification::forUser($userId);
                            $gam->total_xp += $missionXp;
                            $gam->save();
                        }

                        $events[] = [
                            'type' => 'mission_completed',
                            'mission_id' => $um->mission_id,
                            'mission_title' => $um->mission->title,
                            'xp_reward' => $missionXp,
                        ];
                    }
                }
            }
        }

        return $events;
    }

    /**
     * Check extra criteria for mission advancement (e.g., min_score).
     */
    private function meetsExtraMissionCriteria(Mission $mission, array $context): bool
    {
        $criteria = $mission->criteria ?? [];

        if (isset($criteria['min_score']) && isset($context['score_pct'])) {
            return $context['score_pct'] >= $criteria['min_score'];
        }

        if (isset($criteria['min_minutes']) && isset($context['time_minutes'])) {
            return $context['time_minutes'] >= $criteria['min_minutes'];
        }

        return true; // No extra criteria
    }

    /**
     * Ensure daily/weekly missions are assigned for a user.
     * Called on daily login or dashboard load.
     */
    public function ensureMissionsAssigned(int $userId): void
    {
        // Daily missions
        $dailyMissions = Mission::active()->daily()->available()->get();
        foreach ($dailyMissions as $mission) {
            $existing = UserMission::forUser($userId)
                ->where('mission_id', $mission->id)
                ->where('status', 'active')
                ->whereDate('created_at', today())
                ->first();

            if (! $existing) {
                UserMission::create([
                    'user_id' => $userId,
                    'mission_id' => $mission->id,
                    'progress' => 0,
                    'target' => $mission->criteria['target'] ?? 1,
                    'status' => 'active',
                ]);
            }
        }

        // Weekly missions — only assign on Monday or if not assigned this week
        $weeklyMissions = Mission::active()->weekly()->available()->get();
        $weekStart = now()->startOfWeek();

        foreach ($weeklyMissions as $mission) {
            $existing = UserMission::forUser($userId)
                ->where('mission_id', $mission->id)
                ->where('status', 'active')
                ->where('created_at', '>=', $weekStart)
                ->first();

            if (! $existing) {
                UserMission::create([
                    'user_id' => $userId,
                    'mission_id' => $mission->id,
                    'progress' => 0,
                    'target' => $mission->criteria['target'] ?? 1,
                    'status' => 'active',
                ]);
            }
        }
    }

    /**
     * Get gamification summary for display (header bar, etc.).
     */
    public function getSummary(int $userId): array
    {
        $gam = UserGamification::forUser($userId);

        return [
            'total_xp' => $gam->total_xp,
            'level' => $gam->level,
            'level_title' => $gam->levelTitle(),
            'xp_progress_pct' => $gam->xpProgressPct(),
            'xp_for_next_level' => $gam->xpForNextLevel(),
            'current_streak' => $gam->current_streak_days,
            'longest_streak' => $gam->longest_streak_days,
            'protection_tokens' => $gam->streak_protection_tokens,
        ];
    }

    /**
     * Get user's badges (earned and unearned).
     */
    public function getBadges(int $userId): array
    {
        $allBadges = Badge::active()->get();
        $earnedIds = DB::table('user_badges')
            ->where('user_id', $userId)
            ->pluck('badge_id')
            ->toArray();

        return $allBadges->map(fn (Badge $b) => [
            'id' => $b->id,
            'name' => $b->name,
            'slug' => $b->slug,
            'description' => $b->description,
            'icon' => $b->icon,
            'category' => $b->category,
            'xp_reward' => $b->xp_reward,
            'earned' => in_array($b->id, $earnedIds),
            'earned_at' => in_array($b->id, $earnedIds)
                ? DB::table('user_badges')->where('user_id', $userId)->where('badge_id', $b->id)->value('earned_at')
                : null,
        ])->groupBy('category')->toArray();
    }

    /**
     * Get missions for a user (with progress).
     */
    public function getMissions(int $userId): array
    {
        $this->ensureMissionsAssigned($userId);

        $daily = UserMission::forUser($userId)
            ->active()
            ->whereDate('created_at', today())
            ->with('mission')
            ->get()
            ->filter(fn ($um) => $um->mission?->type === 'daily')
            ->values();

        $weekly = UserMission::forUser($userId)
            ->active()
            ->where('created_at', '>=', now()->startOfWeek())
            ->with('mission')
            ->get()
            ->filter(fn ($um) => $um->mission?->type === 'weekly')
            ->values();

        $completed = UserMission::forUser($userId)
            ->completed()
            ->with('mission')
            ->latest('completed_at')
            ->limit(10)
            ->get();

        $transform = fn ($um) => [
            'id' => $um->id,
            'mission_id' => $um->mission_id,
            'title' => $um->mission->title,
            'description' => $um->mission->description,
            'type' => $um->mission->type,
            'progress' => $um->progress,
            'target' => $um->target,
            'progress_pct' => $um->progressPct(),
            'xp_reward' => $um->mission->xp_reward,
            'status' => $um->status,
            'completed_at' => $um->completed_at?->diffForHumans(),
        ];

        return [
            'daily' => $daily->map($transform)->toArray(),
            'weekly' => $weekly->map($transform)->toArray(),
            'completed' => $completed->map($transform)->toArray(),
        ];
    }

    /**
     * Get simple leaderboard data (top 10 + current user position).
     */
    public function getLeaderboard(int $userId, ?int $limit = 10): array
    {
        $peers = app(CommunityAccessService::class)->peers(User::findOrFail($userId))->select('id');
        $top = UserGamification::whereIn('user_id', $peers)->with('user:id,first_name,last_name')
            ->orderByDesc('total_xp')
            ->limit($limit)
            ->get()
            ->map(fn ($g, $i) => [
                'rank' => $i + 1,
                'name' => $g->user->name ?? 'Unknown',
                'xp' => $g->total_xp,
                'level' => $g->level,
                'streak' => $g->current_streak_days,
            ])->toArray();

        // Current user's rank
        $userXp = UserGamification::forUser($userId)->total_xp;
        $rank = UserGamification::whereIn('user_id', $peers)->where('total_xp', '>', $userXp)->count() + 1;

        return [
            'top' => $top,
            'user_rank' => $rank,
            'user_xp' => $userXp,
        ];
    }
}
