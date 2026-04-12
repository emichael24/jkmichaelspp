<?php

namespace App\Http\Controllers;

use App\Models\CourseEnrollment;
use App\Models\DailyActivityLog;
use App\Models\LessonProgress;
use App\Models\QuestionResponse;
use App\Models\ReadinessScore;
use App\Models\UserGamification;
use App\Services\ErrorPatternService;
use App\Services\GamificationService;
use App\Services\SpacedRepetitionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(
        private GamificationService $gamificationService,
        private ErrorPatternService $errorPatternService,
        private SpacedRepetitionService $srService,
    ) {}

    public function __invoke(Request $request)
    {
        $user = $request->user();

        // ── Redirect non-learners to their proper dashboard ──
        $role = $user->getRoleNames()->first() ?? 'learner';
        if ($role !== 'learner') {
            $redirect = match ($role) {
                'platform_admin' => route('admin.dashboard'),
                'facilitator'    => route('facilitator.dashboard'),
                'sme_reviewer'   => route('sme.dashboard'),
                'sponsor'        => route('sponsor.dashboard'),
                default          => null,
            };
            if ($redirect) {
                return redirect($redirect);
            }
        }

        // ── Gamification data ────────────────────────
        $gamSummary = $this->gamificationService->getSummary($user->id);

        // Ensure daily missions are ready & streak is recorded
        $this->gamificationService->ensureMissionsAssigned($user->id);
        $this->gamificationService->awardXp($user->id, 'daily_login');

        // Get active enrollments with course info
        $enrollments = CourseEnrollment::where('user_id', $user->id)
            ->where('status', 'active')
            ->with([
                'course' => fn ($q) => $q->select([
                    'id', 'title', 'slug', 'subtitle', 'thumbnail',
                    'level', 'type', 'duration_hours', 'category_id',
                ])
                ->with('category:id,name,slug')
                ->withCount(['modules', 'lessons']),
            ])
            ->orderByDesc('updated_at')
            ->limit(4)
            ->get();

        // Build "continue" course — the most recently active one
        $continueCourse = null;
        if ($enrollments->isNotEmpty()) {
            $top = $enrollments->first();
            $course = $top->course;

            // Find the next incomplete lesson
            $completedLessonIds = LessonProgress::where('user_id', $user->id)
                ->whereIn('lesson_id', $course->lessons()->pluck('lessons.id'))
                ->where('status', 'completed')
                ->pluck('lesson_id');

            $nextLesson = $course->lessons()
                ->published()
                ->whereNotIn('lessons.id', $completedLessonIds)
                ->orderBy('modules.sort_order')
                ->orderBy('lessons.sort_order')
                ->first();

            $totalLessons = $course->lessons_count;
            $lessonsLeft = $totalLessons - $completedLessonIds->count();

            $continueCourse = [
                'id'            => $course->id,
                'slug'          => $course->slug,
                'title'         => $course->title,
                'thumbnail'     => $course->thumbnail,
                'progress'      => $top->progress_pct,
                'currentLesson' => $nextLesson?->title ?? 'All lessons completed',
                'timeLeft'      => $this->formatDuration($lessonsLeft * 15), // estimate
                'lessonsLeft'   => $lessonsLeft,
            ];
        }

        // Build course cards
        $courses = $enrollments->map(fn ($e) => [
            'id'           => $e->course->id,
            'slug'         => $e->course->slug,
            'title'        => $e->course->title,
            'subtitle'     => $e->course->subtitle,
            'thumbnail'    => $e->course->thumbnail,
            'level'        => $e->course->level,
            'category'     => $e->course->category?->name,
            'progress'     => $e->progress_pct,
            'lessons_count' => $e->course->lessons_count,
            'modules_count' => $e->course->modules_count,
        ]);

        $activeCount = CourseEnrollment::where('user_id', $user->id)
            ->where('status', 'active')
            ->count();

        // ── Readiness score ──────────────────────────
        $latestEnrollment = $enrollments->first();
        $readiness = null;
        if ($latestEnrollment?->course) {
            $readiness = ReadinessScore::latestFor($user->id, $latestEnrollment->course->id);
        }

        // ── Spaced repetition due count ──────────────
        $dueCards = $this->srService->getSessionStats($user->id)['due_today'] ?? 0;

        // ── Today's plan tiles ───────────────────────
        $todayPlan = $this->buildTodayPlan($user, $dueCards, $latestEnrollment);

        // ── Weekly activity (for streak context) ─────
        $weekLogs = DailyActivityLog::forUser($user->id)
            ->inRange(now()->subDays(6)->startOfDay(), now()->endOfDay())
            ->orderBy('date')
            ->get();
        $daysActive = $weekLogs->count();
        $weeklyXp = $weekLogs->sum('xp_earned');

        // ── Coach insight ────────────────────────────
        $coachInsight = $this->errorPatternService->generateCoachInsight($user->id);

        // ── Focus areas (weak domains) ───────────────
        $focusAreas = QuestionResponse::where('question_responses.user_id', $user->id)
            ->join('questions', 'questions.id', '=', 'question_responses.question_id')
            ->whereNotNull('questions.domain')
            ->selectRaw("questions.domain, COUNT(*) as total, SUM(CASE WHEN question_responses.is_correct = 1 THEN 1 ELSE 0 END) as correct")
            ->groupBy('questions.domain')
            ->orderByRaw('SUM(CASE WHEN question_responses.is_correct = 1 THEN 1 ELSE 0 END) * 1.0 / COUNT(*)')
            ->limit(3)
            ->get()
            ->map(fn ($row) => [
                'domain'    => $row->domain,
                'accuracy'  => $row->total > 0 ? round(($row->correct / $row->total) * 100) : 0,
                'total'     => $row->total,
            ]);

        // ── Exam countdown ───────────────────────────
        $examDate = $user->exam_date;
        $examCountdown = null;
        if ($examDate) {
            $daysLeft = (int) now()->diffInDays($examDate, false);
            $examCountdown = [
                'exam_date'     => $examDate,
                'days_left'     => max(0, $daysLeft),
                'is_past'       => $daysLeft < 0,
                'urgency'       => $daysLeft <= 7 ? 'critical' : ($daysLeft <= 14 ? 'high' : ($daysLeft <= 30 ? 'medium' : 'low')),
            ];
        }

        // ── Recent badges ────────────────────────────
        $recentBadges = \DB::table('user_badges')
            ->where('user_id', $user->id)
            ->join('badges', 'badges.id', '=', 'user_badges.badge_id')
            ->orderByDesc('user_badges.earned_at')
            ->limit(3)
            ->get(['badges.name', 'badges.icon', 'badges.category', 'user_badges.earned_at']);

        // ── Missions summary ─────────────────────────
        $missions = $this->gamificationService->getMissions($user->id);
        $dailyMissions = $missions['daily'] ?? [];
        $dailyCompleted = collect($dailyMissions)->where('status', 'completed')->count();

        return Inertia::render('Dashboard', [
            'user' => [
                'first_name'    => $user->first_name,
                'name'          => $user->full_name,
                'level'         => $gamSummary['level'],
                'level_title'   => $gamSummary['level_title'],
                'xp'            => $gamSummary['total_xp'],
                'xp_progress'   => $gamSummary['xp_progress_pct'],
                'next_level_xp' => $gamSummary['xp_for_next_level'],
                'streak'        => $gamSummary['current_streak'],
                'longest_streak' => $gamSummary['longest_streak'],
                'protection_tokens' => $gamSummary['protection_tokens'],
            ],
            'stats' => [
                'streak'        => $gamSummary['current_streak'],
                'daysActive'    => $daysActive,
                'weeklyXp'      => $weeklyXp,
                'activeCourses' => $activeCount,
                'readiness'     => $readiness?->score_pct ?? 0,
                'passLikelihood' => $readiness?->pass_likelihood ?? null,
                'confidenceBand' => $readiness?->confidence_band ?? null,
            ],
            'todayPlan'      => $todayPlan,
            'continueCourse' => $continueCourse,
            'courses'        => $courses,
            'dueCards'       => $dueCards,
            'coachInsight'   => $coachInsight,
            'recentBadges'   => $recentBadges,
            'dailyMissions'  => $dailyMissions,
            'dailyMissionsCompleted' => $dailyCompleted,
            'dailyMissionsTotal'     => count($dailyMissions),
            'focusAreas'     => $focusAreas,
            'examCountdown'  => $examCountdown,
        ]);
    }

    /**
     * Build today's adaptive plan (max 3 tiles).
     */
    private function buildTodayPlan($user, int $dueCards, $latestEnrollment): array
    {
        $plan = [];

        // 1. Flashcard review if due cards exist
        if ($dueCards > 0) {
            $plan[] = [
                'type'     => 'flashcard_review',
                'title'    => 'Review Flashcards',
                'subtitle' => "{$dueCards} cards due",
                'time'     => min($dueCards * 0.5, 15) . ' min',
                'url'      => '/practice/review',
                'icon'     => 'brain',
            ];
        }

        // 2. Continue lesson if enrolled
        if ($latestEnrollment?->course) {
            $course = $latestEnrollment->course;
            $completedIds = LessonProgress::where('user_id', $user->id)
                ->whereIn('lesson_id', $course->lessons()->pluck('lessons.id'))
                ->where('status', 'completed')
                ->pluck('lesson_id');

            $nextLesson = $course->lessons()
                ->published()
                ->whereNotIn('lessons.id', $completedIds)
                ->orderBy('modules.sort_order')
                ->orderBy('lessons.sort_order')
                ->first();

            if ($nextLesson) {
                $plan[] = [
                    'type'     => 'lesson',
                    'title'    => 'Continue Lesson',
                    'subtitle' => $nextLesson->title,
                    'time'     => ($nextLesson->duration_minutes ?? 15) . ' min',
                    'url'      => "/courses/{$course->slug}/lessons/{$nextLesson->id}",
                    'icon'     => 'book-open',
                ];
            }
        }

        // 3. Practice quiz or mock exam suggestion
        if (count($plan) < 3) {
            $plan[] = [
                'type'     => 'quiz',
                'title'    => 'Practice Quiz',
                'subtitle' => 'Strengthen weak areas',
                'time'     => '10 min',
                'url'      => '/quizzes',
                'icon'     => 'clipboard-check',
            ];
        }

        return array_slice($plan, 0, 3);
    }

    private function formatDuration(int $minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes} min";
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        return $mins > 0 ? "{$hours}h {$mins}m" : "{$hours}h";
    }
}
