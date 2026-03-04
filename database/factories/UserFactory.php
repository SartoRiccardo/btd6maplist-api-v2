<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'discord_id' => fake()->unique()->numerify('1#################'),
            'name' => fake()->userName() . fake()->randomLetter() . fake()->randomLetter() . fake()->randomNumber(3, true),
            'nk_oak' => null,
            'has_seen_popup' => false,
            'is_banned' => false,
        ];
    }
}
