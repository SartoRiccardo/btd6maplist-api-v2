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
            'cached_avatar_url' => null,
            'cached_banner_url' => null,
            'ninjakiwi_cache_expire' => null,
        ];
    }

    /**
     * Indicate that the user has cached flair (avatar and banner URLs).
     */
    public function cachedFlair(?string $avatarUrl = null, ?string $bannerUrl = null): static
    {
        return $this->state(fn (array $attributes) => [
            'cached_avatar_url' => $avatarUrl ?? 'https://example.com/avatar.png',
            'cached_banner_url' => $bannerUrl ?? 'https://example.com/banner.png',
            'ninjakiwi_cache_expire' => now()->addMinutes(10),
        ]);
    }

    /**
     * Indicate that the user has a Ninja Kiwi OAK.
     */
    public function withOak(string $oak): static
    {
        return $this->state(fn (array $attributes) => [
            'nk_oak' => $oak,
        ]);
    }
}
