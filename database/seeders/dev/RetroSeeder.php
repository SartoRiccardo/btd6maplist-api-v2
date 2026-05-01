<?php

namespace Database\Seeders\Dev;

use App\Models\RetroGame;
use App\Models\RetroMap;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory;

class RetroSeeder extends Seeder
{
    use WithoutModelEvents;

    private const int MAPS_PER_GAME = 10;

    private \Faker\Generator $faker;

    public function __construct()
    {
        $this->faker = Factory::create();
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $games = RetroGame::all();
        $this->command->info("Creating retro maps for {$games->count()} games...");

        $mapsToInsert = [];
        foreach ($games as $game) {
            for ($i = 0; $i < self::MAPS_PER_GAME; $i++) {
                $mapsToInsert[] = [
                    'name' => $this->faker->words(3, true),
                    'sort_order' => $i + 1,
                    'preview_url' => $this->faker->url(),
                    'retro_game_id' => $game->id,
                ];
            }
        }

        RetroMap::insertOrIgnore($mapsToInsert);
        $this->command->info("Created " . count($mapsToInsert) . " retro maps.");
    }
}
