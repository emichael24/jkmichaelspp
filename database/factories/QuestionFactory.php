<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestionFactory extends Factory
{
    protected $model = Question::class;

    public function definition(): array
    {
        return ['course_id' => Course::factory(), 'domain' => 'General', 'type' => 'multiple_choice', 'difficulty' => 'easy',
            'stem' => fake()->sentence().'?', 'options' => [['id' => 'A', 'text' => 'First'], ['id' => 'B', 'text' => 'Second']],
            'correct_answer_ids' => ['A'], 'explanation_correct' => 'The first answer applies.', 'time_estimate_seconds' => 30, 'status' => 'published'];
    }
}
