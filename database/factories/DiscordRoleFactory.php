<?php

namespace Database\Factories;

use App\Models\AchievementRole;
use App\Models\DiscordRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DiscordRole>
 */
class DiscordRoleFactory extends Factory
{
    protected $model = DiscordRole::class;

    public function definition(): array
    {
        return [
            'achievement_role_id' => AchievementRole::factory(),
            'guild_id' => fake()->unique()->randomNumber(5, true) . str_repeat('0', 12),
            'role_id' => fake()->unique()->randomNumber(5, true) . str_repeat('0', 12),
        ];
    }

    public function forAchievementRole(AchievementRole $role): self
    {
        return $this->state(fn(array $attributes) => [
            'achievement_role_id' => $role->id,
        ]);
    }
}
