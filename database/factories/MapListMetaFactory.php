<?php

namespace Database\Factories;

use App\Models\Map;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MapListMeta>
 */
class MapListMetaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $heroes = [
            'Quincy',
            'Gwendolin',
            'Striker Jones',
            'Churchill',
            'Benjamin',
            'Etienne',
            'Obyn Greenfoot',
            'Captain Churchill',
            'Ninja Monkey',
            'Alchemist',
            'Super Monkey',
            'Wizard',
            'Necromancer',
            'Elite Sniper',
            'Prince of Darkness',
            'Adora',
            'Brickell',
            'Pat Fusty',
            'Sauda',
            'Psi',
            'Etienne',
            'Geraldo',
            'Rosalia',
            'Dan',
        ];

        $optimalHeroes = [];
        if (fake()->boolean(60)) {
            $optimalHeroes = fake()->randomElements($heroes, fake()->numberBetween(0, 3));
        }

        return [
            'code' => fn() => Map::factory()->create()->code,
            'placement_curver' => fake()->optional(0.7)->numberBetween(1, 100),
            'placement_allver' => fake()->optional(0.7)->numberBetween(1, 100),
            'difficulty' => fake()->optional(0.5)->numberBetween(0, 4),
            'optimal_heros' => $optimalHeroes,
            'botb_difficulty' => fake()->optional(0.5)->numberBetween(0, 4),
            'remake_of' => null,
            'created_on' => fake()->dateTimeBetween('-1 year', 'now'),
            'deleted_on' => null,
        ];
    }

    public function empty(): static
    {
        return $this->state(fn(array $attributes) => [
            'placement_curver' => null,
            'placement_allver' => null,
            'difficulty' => null,
            'botb_difficulty' => null,
            'remake_of' => null,
        ]);
    }
}
