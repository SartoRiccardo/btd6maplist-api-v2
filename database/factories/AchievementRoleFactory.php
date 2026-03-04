<?php

namespace Database\Factories;

use App\Models\AchievementRole;
use App\Models\Format;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AchievementRole>
 */
class AchievementRoleFactory extends Factory
{
    protected $model = AchievementRole::class;

    public function definition(): array
    {
        return [
            'lb_format' => Format::factory(),
            'lb_type' => 'points',
            'threshold' => fake()->unique()->randomNumber(3, true), // Unique threshold
            'for_first' => false,
            'tooltip_description' => fake()->optional()->sentence(),
            'name' => fake()->words(2, true),
            'clr_border' => fake()->numberBetween(0, 0xFFFFFF),
            'clr_inner' => fake()->numberBetween(0, 0xFFFFFF),
        ];
    }

    public function firstPlace(): static
    {
        return $this->state(fn(array $attributes) => [
            'for_first' => true,
            'threshold' => 0,
        ]);
    }
}
