<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\LearnerAnalyticsService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LearnerAnalyticsController extends Controller
{
    protected $analyticsService;

    public function __construct(LearnerAnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    public function showDashboard(Request $request, $courseId)
    {
        $user = Auth::user();

        $readinessScore = $this->analyticsService->getReadinessScore($user->id, $courseId);
        $domainMastery = $this->analyticsService->getDomainMastery($user->id, $courseId);
        $examHistory = $this->analyticsService->getExamHistory($user->id, $courseId);

        return Inertia::render('Analytics/LearnerDashboard', [
            'courseId' => $courseId,
            'readinessScore' => $readinessScore,
            'domainMastery' => $domainMastery,
            'examHistory' => $examHistory
        ]);
    }
}

