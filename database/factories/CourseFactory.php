<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        return ['title' => fake()->sentence(4), 'slug' => fake()->unique()->slug(),
            'description' => 'A practical introduction with guided exercises.', 'learning_outcomes' => ['Apply the core concepts'],
            'category_id' => fn () => Category::firstOrCreate(['slug' => 'general'], ['name' => 'General', 'is_active' => true])->id,
            'author_id' => User::factory(), 'level' => 'beginner', 'type' => 'self_paced', 'price' => 0, 'currency' => 'NGN',
            'is_published' => true, 'published_at' => fn (array $attributes) => $attributes['is_published'] ? now() : null];
    }
}
