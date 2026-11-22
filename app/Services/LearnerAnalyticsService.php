<?php

namespace App\Services;

use App\Models\Course;
use App\Models\MockExam;
use App\Models\QuizAttempt;

class LearnerAnalyticsService
{
    /**
     * Calculate global readiness score based on recent mock exams.
     * Often represents probability of passing actual certification.
     */
    public function getReadinessScore($userId, $courseId)
    {
        // Get last 3 completed mock exams for this course
        $attempts = QuizAttempt::where('user_id', $userId)
            ->where('status', 'completed')
            ->whereHasMorph('quizzable', [MockExam::class], function ($query) use ($courseId) {
                $query->where('course_id', $courseId);
            })
            ->orderBy('completed_at', 'desc')
            ->take(3)
            ->get();

        if ($attempts->isEmpty()) {
            return 0;
        }

        // Weighted moving average: Most recent exam matters most
        $weights = [0.5, 0.3, 0.2];
        $score = 0;
        $totalWeight = 0;

        foreach ($attempts as $index => $attempt) {
            $weight = $weights[$index] ?? 0.1;
            $score += $attempt->score_pct * $weight;
            $totalWeight += $weight;
        }

        return round($score / $totalWeight, 1);
    }

    /**
     * Aggregate all domain scores from all quiz attempts
     * to build a "Mastery Map" of weak/strong areas.
     */
    public function getDomainMastery($userId, $courseId)
    {
        $attempts = QuizAttempt::where('user_id', $userId)
            ->where('status', 'completed')
            // Using whereNotNull to only grab attempts that had domain tracking
            ->whereNotNull('domain_scores')
            ->get();

        $mastery = [];

        foreach ($attempts as $attempt) {
            $domainScores = json_decode($attempt->domain_scores, true);
            if (! $domainScores) {
                continue;
            }

            foreach ($domainScores as $domain => $stats) {
                if (! isset($mastery[$domain])) {
                    $mastery[$domain] = ['correct' => 0, 'total' => 0, 'percentage' => 0];
                }
                $mastery[$domain]['correct'] += $stats['correct'];
                $mastery[$domain]['total'] += $stats['total'];
            }
        }

        // Calculate percentages
        foreach ($mastery as $domain => &$data) {
            if ($data['total'] > 0) {
                $data['percentage'] = round(($data['correct'] / $data['total']) * 100);
            }

            // Assign a status band
            if ($data['percentage'] >= 80) {
                $data['status'] = 'Proficient';
            } elseif ($data['percentage'] >= 60) {
                $data['status'] = 'Intermediate';
            } else {
                $data['status'] = 'Needs Review';
            }
        }

        // Sort by lowest percentage to highlight weak areas first
        uasort($mastery, function ($a, $b) {
            return $a['percentage'] <=> $b['percentage'];
        });

        return $mastery;
    }

    /**
     * Get historical trend line for charts
     */
    public function getExamHistory($userId, $courseId)
    {
        return QuizAttempt::where('user_id', $userId)
            ->where('status', 'completed')
            ->orderBy('completed_at', 'asc') // Chronological for charts
            ->get()
            ->map(function ($attempt) {
                return [
                    'id' => $attempt->id,
                    'title' => $attempt->quizzable->title ?? 'Assessment',
                    'date' => $attempt->completed_at->format('M j, Y'),
                    'score' => $attempt->score_pct,
                    'time_spent' => gmdate('i:s', $attempt->time_spent_seconds),
                ];
            });
    }
}
