<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\MockExam;
use Illuminate\Database\Eloquent\Factories\Factory;

class MockExamFactory extends Factory
{
    protected $model = MockExam::class;

    public function definition(): array
    {
        return ['course_id' => Course::factory(), 'title' => 'Practice exam', 'type' => 'mini_mock',
            'question_count' => 5, 'time_limit_minutes' => 60, 'passing_score_pct' => 70, 'is_published' => true,
            'allow_pause' => true, 'domain_distribution' => ['General' => 5]];
    }
}
