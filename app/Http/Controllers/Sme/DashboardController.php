<?php

namespace App\Http\Controllers\Sme;

use App\Http\Controllers\Controller;
use App\Models\ContentApproval;
use App\Models\Question;
use App\Models\QuestionFlag;
use App\Models\QuestionResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $userId = Auth::id();

        // Content review stats
        $pendingReview = ContentApproval::where('status', 'pending')->count();
        $reviewedThisMonth = ContentApproval::whereIn('status', ['approved', 'rejected', 'changes_requested'])
            ->where('reviewed_by', $userId)
            ->where('updated_at', '>=', now()->subMonth())
            ->count();

        // Question bank stats
        $totalQuestions = Question::count();
        $publishedQuestions = Question::where('status', 'published')->count();
        $draftQuestions = Question::where('status', 'draft')->count();
        $inReviewQuestions = Question::where('status', 'review')->count();

        // Flag queue
        $openFlags = QuestionFlag::where('status', 'open')->count();
        $recentFlags = QuestionFlag::where('status', 'open')
            ->with(['question:id,stem,difficulty', 'reporter:id,first_name,last_name'])
            ->latest()
            ->limit(5)
            ->get();

        // Quality metrics - difficulty distribution
        $difficultyDist = Question::where('status', 'published')
            ->selectRaw('difficulty, COUNT(*) as count')
            ->groupBy('difficulty')
            ->pluck('count', 'difficulty');

        // Recent responses for accuracy tracking
        $avgAccuracy = QuestionResponse::where('created_at', '>=', now()->subDays(30))
            ->selectRaw('ROUND(AVG(CASE WHEN is_correct = 1 THEN 100 ELSE 0 END), 1) as accuracy')
            ->value('accuracy') ?? 0;

        // Items needing attention (low discrimination or very high/low difficulty)
        $problemItems = Question::where('status', 'published')
            ->where(function ($q) {
                $q->where('discrimination_index', '<', 0.15)
                    ->orWhereRaw('correct_rate < 20 OR correct_rate > 95');
            })
            ->select('id', 'stem', 'difficulty', 'discrimination_index', 'correct_rate')
            ->limit(5)
            ->get();

        // Content pending review - most recent items
        $pendingContent = ContentApproval::where('status', 'pending')
            ->with(['submitter:id,first_name,last_name'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'type' => $item->contentable_type ? class_basename($item->contentable_type) : 'Unknown',
                'title' => $item->title ?? 'Untitled',
                'submitter' => $item->submitter ? $item->submitter->first_name.' '.$item->submitter->last_name : 'Unknown',
                'submitted' => $item->created_at?->diffForHumans(),
            ]);

        // My review activity this week
        $weeklyActivity = ContentApproval::where('reviewed_by', $userId)
            ->where('updated_at', '>=', now()->startOfWeek())
            ->selectRaw('DATE(updated_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date');

        return Inertia::render('Sme/Dashboard', [
            'stats' => [
                'pendingReview' => $pendingReview,
                'reviewedThisMonth' => $reviewedThisMonth,
                'totalQuestions' => $totalQuestions,
                'publishedQuestions' => $publishedQuestions,
                'draftQuestions' => $draftQuestions,
                'inReviewQuestions' => $inReviewQuestions,
                'openFlags' => $openFlags,
                'avgAccuracy' => $avgAccuracy,
            ],
            'difficultyDist' => $difficultyDist,
            'recentFlags' => $recentFlags,
            'problemItems' => $problemItems,
            'pendingContent' => $pendingContent,
            'weeklyActivity' => $weeklyActivity,
        ]);
    }
}
