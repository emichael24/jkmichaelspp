<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
            // Learning badges
            ['name' => 'First Lesson', 'slug' => 'first-lesson', 'description' => 'Complete your first lesson', 'category' => 'learning', 'criteria' => ['type' => 'lessons_completed', 'value' => 1], 'xp_reward' => 10],
            ['name' => '10 Lessons', 'slug' => '10-lessons', 'description' => 'Complete 10 lessons', 'category' => 'learning', 'criteria' => ['type' => 'lessons_completed', 'value' => 10], 'xp_reward' => 25],
            ['name' => '50 Lessons', 'slug' => '50-lessons', 'description' => 'Complete 50 lessons', 'category' => 'learning', 'criteria' => ['type' => 'lessons_completed', 'value' => 50], 'xp_reward' => 100],

            // Mastery badges
            ['name' => 'Domain Master', 'slug' => 'domain-master', 'description' => 'Achieve 90%+ accuracy in any domain', 'category' => 'mastery', 'criteria' => ['type' => 'domain_accuracy', 'value' => 90], 'xp_reward' => 75],
            ['name' => 'Full Spectrum', 'slug' => 'full-spectrum', 'description' => 'Achieve 70%+ accuracy in all domains', 'category' => 'mastery', 'criteria' => ['type' => 'all_domains_accuracy', 'value' => 70], 'xp_reward' => 150],

            // Streak badges
            ['name' => 'Week Warrior', 'slug' => '7-day-streak', 'description' => 'Maintain a 7-day study streak', 'category' => 'streak', 'criteria' => ['type' => 'streak', 'value' => 7], 'xp_reward' => 50],
            ['name' => 'Monthly Momentum', 'slug' => '30-day-streak', 'description' => 'Maintain a 30-day study streak', 'category' => 'streak', 'criteria' => ['type' => 'streak', 'value' => 30], 'xp_reward' => 150],
            ['name' => 'Centurion', 'slug' => '100-day-streak', 'description' => 'Maintain a 100-day study streak', 'category' => 'streak', 'criteria' => ['type' => 'streak', 'value' => 100], 'xp_reward' => 500],

            // Completion badges
            ['name' => 'Course Graduate', 'slug' => 'course-complete', 'description' => 'Complete an entire course', 'category' => 'completion', 'criteria' => ['type' => 'courses_completed', 'value' => 1], 'xp_reward' => 100],
            ['name' => 'Mock Exam Ace', 'slug' => 'mock-exam-ace', 'description' => 'Score 90%+ on a mock exam', 'category' => 'completion', 'criteria' => ['type' => 'mock_score', 'value' => 90], 'xp_reward' => 75],
            ['name' => 'Quiz Master', 'slug' => 'quiz-master', 'description' => 'Pass 50 quizzes', 'category' => 'completion', 'criteria' => ['type' => 'quizzes_passed', 'value' => 50], 'xp_reward' => 100],

            // Social badges
            ['name' => 'First Post', 'slug' => 'first-forum-post', 'description' => 'Create your first forum post', 'category' => 'social', 'criteria' => ['type' => 'forum_posts', 'value' => 1], 'xp_reward' => 10],
            ['name' => 'Helpful Answer', 'slug' => 'helpful-answer', 'description' => 'Have a forum answer marked as helpful', 'category' => 'social', 'criteria' => ['type' => 'helpful_answers', 'value' => 1], 'xp_reward' => 25],

            // Internship badges
            ['name' => 'First Submission', 'slug' => 'first-submission', 'description' => 'Submit your first internship task', 'category' => 'internship', 'criteria' => ['type' => 'internship_tasks', 'value' => 1], 'xp_reward' => 20],
            ['name' => 'Milestone Complete', 'slug' => 'milestone-complete', 'description' => 'Complete an internship milestone', 'category' => 'internship', 'criteria' => ['type' => 'milestones_completed', 'value' => 1], 'xp_reward' => 50],
            ['name' => 'Portfolio Builder', 'slug' => 'portfolio-builder', 'description' => 'Add 5 items to your portfolio', 'category' => 'internship', 'criteria' => ['type' => 'portfolio_items', 'value' => 5], 'xp_reward' => 50],
        ];

        foreach ($badges as $badge) {
            Badge::firstOrCreate(['slug' => $badge['slug']], $badge);
        }
    }
}
