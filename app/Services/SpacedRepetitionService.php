<?php

namespace App\Services;

use App\Models\Flashcard;
use App\Models\Question;
use App\Models\QuizAttempt;
use App\Models\SpacedRepetitionItem;
use Illuminate\Support\Collection;

class SpacedRepetitionService
{
    /**
     * After a quiz/mock is completed, auto-create flashcards + SR items
     * for every wrong answer.
     */
    public function processCompletedAttempt(QuizAttempt $attempt): void
    {
        $wrongResponses = $attempt->responses()
            ->where('is_correct', false)
            ->with('question')
            ->get();

        foreach ($wrongResponses as $response) {
            $question = $response->question;
            if (! $question) continue;

            // Create a "Fix Card" flashcard
            $flashcard = Flashcard::createFromWrongAnswer($question, $attempt->user_id);

            // Create the SR item for the flashcard
            SpacedRepetitionItem::ensureExists(
                $attempt->user_id,
                Flashcard::class,
                $flashcard->id
            );
        }
    }

    /**
     * Get due items for review, interleaved across domains.
     */
    public function getDueItems(int $userId, int $limit = 20): Collection
    {
        $items = SpacedRepetitionItem::forUser($userId)
            ->active()
            ->dueNow()
            ->with('reviewable')
            ->orderBy('next_review_at')
            ->limit($limit * 2) // fetch extra to allow interleaving
            ->get();

        return $this->interleave($items)->take($limit);
    }

    /**
     * Get stats for a user's SR session.
     */
    public function getSessionStats(int $userId): array
    {
        $items = SpacedRepetitionItem::forUser($userId)->active();

        return [
            'due_today'    => (clone $items)->dueNow()->count(),
            'total_active' => (clone $items)->count(),
            'new'          => SpacedRepetitionItem::forUser($userId)->where('status', 'new')->count(),
            'learning'     => SpacedRepetitionItem::forUser($userId)->where('status', 'learning')->count(),
            'review'       => SpacedRepetitionItem::forUser($userId)->where('status', 'review')->count(),
            'graduated'    => SpacedRepetitionItem::forUser($userId)->graduated()->count(),
        ];
    }

    /**
     * Process a single review submission. Returns updated item.
     */
    public function submitReview(SpacedRepetitionItem $item, int $quality): SpacedRepetitionItem
    {
        $item->processReview($quality);

        return $item->fresh();
    }

    /**
     * Generate a 7-day improvement plan from a mock exam attempt.
     */
    public function generate7DayPlan(QuizAttempt $attempt): array
    {
        $domainScores = $attempt->domain_scores ?? [];

        // Sort domains by weakness (lowest pct first)
        $sorted = collect($domainScores)->sortBy('pct');

        // Identify top 5 fix priorities
        $fixPriorities = $sorted->take(5)->map(fn ($data, $domain) => [
            'domain'       => $domain,
            'current_pct'  => $data['pct'],
            'correct'      => $data['correct'],
            'total'        => $data['total'],
            'gap_to_pass'  => max(0, ($attempt->quizzable->passing_score_pct ?? 70) - $data['pct']),
        ])->values()->toArray();

        // Build 7-day plan
        $plan = [];
        $weakDomains = $sorted->take(5)->keys()->values();

        for ($day = 1; $day <= 7; $day++) {
            $activities = [];

            // Rotate through weak domains
            $focusDomain = $weakDomains[($day - 1) % $weakDomains->count()] ?? null;

            if ($focusDomain) {
                $activities[] = [
                    'type'        => 'review_lesson',
                    'domain'      => $focusDomain,
                    'description' => "Review lessons in: {$focusDomain}",
                    'duration'    => 20,
                ];

                $activities[] = [
                    'type'        => 'practice_quiz',
                    'domain'      => $focusDomain,
                    'description' => "Practice 10 questions in: {$focusDomain}",
                    'duration'    => 15,
                ];
            }

            // Daily flashcard review every day
            $activities[] = [
                'type'        => 'flashcard_review',
                'domain'      => null,
                'description' => 'Review due flashcards (spaced repetition)',
                'duration'    => 10,
            ];

            // Every other day: mixed domain quiz
            if ($day % 2 === 0) {
                $activities[] = [
                    'type'        => 'mixed_quiz',
                    'domain'      => null,
                    'description' => 'Mixed domain practice (20 questions)',
                    'duration'    => 20,
                ];
            }

            // Day 7: mini mock
            if ($day === 7) {
                $activities[] = [
                    'type'        => 'mini_mock',
                    'domain'      => null,
                    'description' => 'Mini mock exam (50 questions)',
                    'duration'    => 60,
                ];
            }

            $plan[] = [
                'day'        => $day,
                'focus'      => $focusDomain,
                'activities' => $activities,
                'est_time'   => collect($activities)->sum('duration'),
            ];
        }

        return [
            'fix_priorities' => $fixPriorities,
            'plan'           => $plan,
        ];
    }

    /**
     * Interleave items across domains so the review session mixes topics.
     */
    private function interleave(Collection $items): Collection
    {
        // Group items by domain
        $grouped = $items->groupBy(function ($item) {
            $reviewable = $item->reviewable;

            if ($reviewable instanceof Flashcard) {
                return $reviewable->domain ?? 'general';
            }

            if ($reviewable instanceof Question) {
                return $reviewable->domain ?? 'general';
            }

            return 'general';
        });

        // Round-robin through groups
        $result = collect();
        $maxCount = $grouped->max(fn ($group) => $group->count());

        for ($i = 0; $i < $maxCount; $i++) {
            foreach ($grouped as $group) {
                if (isset($group[$i])) {
                    $result->push($group[$i]);
                }
            }
        }

        return $result;
    }
}
