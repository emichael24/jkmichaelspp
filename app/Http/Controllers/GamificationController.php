<?php

namespace App\Http\Controllers;

use App\Services\GamificationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GamificationController extends Controller
{
    public function __construct(
        private GamificationService $gamificationService,
    ) {}

    /**
     * Badges page — all badges, earned/unearned.
     */
    public function badges(Request $request)
    {
        $badges = $this->gamificationService->getBadges($request->user()->id);
        $summary = $this->gamificationService->getSummary($request->user()->id);

        $totalEarned = collect($badges)->flatten(1)->where('earned', true)->count();
        $totalBadges = collect($badges)->flatten(1)->count();

        return Inertia::render('Gamification/Badges', [
            'badges'      => $badges,
            'summary'     => $summary,
            'totalEarned' => $totalEarned,
            'totalBadges' => $totalBadges,
        ]);
    }

    /**
     * Missions page — daily/weekly missions with progress.
     */
    public function missions(Request $request)
    {
        $missions = $this->gamificationService->getMissions($request->user()->id);
        $summary = $this->gamificationService->getSummary($request->user()->id);

        return Inertia::render('Gamification/Missions', [
            'missions' => $missions,
            'summary'  => $summary,
        ]);
    }

    /**
     * Leaderboard page.
     */
    public function leaderboard(Request $request)
    {
        $data = $this->gamificationService->getLeaderboard($request->user()->id);
        $summary = $this->gamificationService->getSummary($request->user()->id);

        return Inertia::render('Gamification/Leaderboard', [
            'leaderboard' => $data,
            'summary'     => $summary,
        ]);
    }

    /**
     * API: Gamification summary (for header bar).
     */
    public function summary(Request $request)
    {
        return response()->json(
            $this->gamificationService->getSummary($request->user()->id)
        );
    }
}
