<?php

namespace Database\Factories;

use App\Models\Format;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Format>
 */
class FormatFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => fake()->unique()->randomNumber(5, true),
            'name' => fake()->words(2, true),
            'hidden' => false,
            'run_submission_status' => fake()->randomElement(['closed', 'open', 'lcc_only']),
            'map_submission_status' => fake()->randomElement(['closed', 'open', 'open_chimps']),
            'map_submission_wh' => fake()->optional()->url(),
            'run_submission_wh' => fake()->optional()->url(),
            'emoji' => fake()->optional()->emoji(),
            'proposed_difficulties' => fake()->optional(0.7)->randomElement([
                ["Easy", "Medium", "Hard"],
                ["Beginner", "Intermediate", "Advanced"],
                ["Casual", "Regular", "Expert"],
            ]),
            'slug' => '',
            'description' => '',
            'button_text' => 'Submit',
            'map_submission_rules' => '',
            'completion_submission_rules' => '',
            'discord_server_url' => null,
        ];
    }

    /**
     * Create a hidden format.
     */
    public function hidden(): self
    {
        return $this->state(fn(array $attributes) => [
            'hidden' => true,
        ]);
    }

    /**
     * Create a format with open submissions.
     */
    public function openSubmissions(): self
    {
        return $this->state(fn(array $attributes) => [
            'run_submission_status' => 'open',
            'map_submission_status' => 'open',
        ]);
    }
}
