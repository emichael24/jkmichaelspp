<?php

namespace Database\Seeders;

use App\Models\Mission;
use Illuminate\Database\Seeder;

class MissionSeeder extends Seeder
{
    public function run(): void
    {
        $missions = [
            // Daily missions
            ['title' => 'Daily Learner', 'description' => 'Complete 1 lesson today', 'type' => 'daily', 'criteria' => ['action' => 'lessons_completed', 'count' => 1], 'xp_reward' => 10],
            ['title' => 'Question Machine', 'description' => 'Answer 20 questions today', 'type' => 'daily', 'criteria' => ['action' => 'questions_answered', 'count' => 20], 'xp_reward' => 15],
            ['title' => 'Memory Keeper', 'description' => 'Review your flashcards today', 'type' => 'daily', 'criteria' => ['action' => 'flashcards_reviewed', 'count' => 5], 'xp_reward' => 10],
            ['title' => 'Show Up', 'description' => 'Log into the app today', 'type' => 'daily', 'criteria' => ['action' => 'daily_login', 'count' => 1], 'xp_reward' => 5],

            // Weekly missions
            ['title' => 'Module Mastery', 'description' => 'Complete 1 module this week', 'type' => 'weekly', 'criteria' => ['action' => 'modules_completed', 'count' => 1], 'xp_reward' => 30],
            ['title' => 'Mock Challenger', 'description' => 'Take 1 mock exam this week', 'type' => 'weekly', 'criteria' => ['action' => 'mocks_completed', 'count' => 1], 'xp_reward' => 25],
            ['title' => 'High Scorer', 'description' => 'Score 80%+ on any practice quiz', 'type' => 'weekly', 'criteria' => ['action' => 'quiz_score_above', 'count' => 1, 'threshold' => 80], 'xp_reward' => 20],
            ['title' => 'Study Marathon', 'description' => 'Study for 120 minutes this week', 'type' => 'weekly', 'criteria' => ['action' => 'time_spent_minutes', 'count' => 120], 'xp_reward' => 25],
        ];

        foreach ($missions as $mission) {
            Mission::firstOrCreate(['title' => $mission['title'], 'type' => $mission['type']], $mission);
        }
    }
}
