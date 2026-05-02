<?php

namespace Database\Seeders\Core;

use App\Models\RetroGame;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RetroGameSeeder extends Seeder
{
    use WithoutModelEvents;

    private static array $games = [
        ['game_id' => 0, 'category_id' => 0, 'subcategory_id' => 0, 'game_name' => 'BTD1-BTD3', 'category_name' => 'Bloons Tower Defense 1', 'subcategory_name' => null],
        ['game_id' => 0, 'category_id' => 1, 'subcategory_id' => 0, 'game_name' => 'BTD1-BTD3', 'category_name' => 'Bloons Tower Defense 2', 'subcategory_name' => null],
        ['game_id' => 0, 'category_id' => 2, 'subcategory_id' => 0, 'game_name' => 'BTD1-BTD3', 'category_name' => 'Bloons Tower Defense 3', 'subcategory_name' => null],
        ['game_id' => 1, 'category_id' => 0, 'subcategory_id' => 0, 'game_name' => 'BTD iOS/PSN/DSi', 'category_name' => 'Bloons TD iOS Exclusive', 'subcategory_name' => null],
        ['game_id' => 1, 'category_id' => 1, 'subcategory_id' => 0, 'game_name' => 'BTD iOS/PSN/DSi', 'category_name' => 'Bloons TD PSN Exclusive', 'subcategory_name' => null],
        ['game_id' => 1, 'category_id' => 2, 'subcategory_id' => 0, 'game_name' => 'BTD iOS/PSN/DSi', 'category_name' => 'Bloons TD DSi Exclusive', 'subcategory_name' => null],
        ['game_id' => 2, 'category_id' => 0, 'subcategory_id' => 0, 'game_name' => 'BTD4', 'category_name' => 'Beginner', 'subcategory_name' => 'General maps'],
        ['game_id' => 2, 'category_id' => 1, 'subcategory_id' => 0, 'game_name' => 'BTD4', 'category_name' => 'Intermediate', 'subcategory_name' => 'General maps'],
        ['game_id' => 2, 'category_id' => 2, 'subcategory_id' => 0, 'game_name' => 'BTD4', 'category_name' => 'Advanced', 'subcategory_name' => 'General maps'],
        ['game_id' => 2, 'category_id' => 3, 'subcategory_id' => 0, 'game_name' => 'BTD4', 'category_name' => 'Expert', 'subcategory_name' => 'General maps'],
        ['game_id' => 2, 'category_id' => 0, 'subcategory_id' => 1, 'game_name' => 'BTD4', 'category_name' => 'Beginner', 'subcategory_name' => 'Premium maps'],
        ['game_id' => 2, 'category_id' => 1, 'subcategory_id' => 1, 'game_name' => 'BTD4', 'category_name' => 'Intermediate', 'subcategory_name' => 'Premium maps'],
        ['game_id' => 2, 'category_id' => 2, 'subcategory_id' => 1, 'game_name' => 'BTD4', 'category_name' => 'Advanced', 'subcategory_name' => 'Premium maps'],
        ['game_id' => 2, 'category_id' => 3, 'subcategory_id' => 1, 'game_name' => 'BTD4', 'category_name' => 'Expert', 'subcategory_name' => 'Premium maps'],
        ['game_id' => 2, 'category_id' => 0, 'subcategory_id' => 2, 'game_name' => 'BTD4', 'category_name' => 'Beginner', 'subcategory_name' => 'Expansion maps'],
        ['game_id' => 2, 'category_id' => 1, 'subcategory_id' => 2, 'game_name' => 'BTD4', 'category_name' => 'Intermediate', 'subcategory_name' => 'Expansion maps'],
        ['game_id' => 2, 'category_id' => 2, 'subcategory_id' => 2, 'game_name' => 'BTD4', 'category_name' => 'Advanced', 'subcategory_name' => 'Expansion maps'],
        ['game_id' => 2, 'category_id' => 3, 'subcategory_id' => 2, 'game_name' => 'BTD4', 'category_name' => 'Expert', 'subcategory_name' => 'Expansion maps'],
        ['game_id' => 2, 'category_id' => 0, 'subcategory_id' => 3, 'game_name' => 'BTD4', 'category_name' => 'Beginner', 'subcategory_name' => 'Mobile maps'],
        ['game_id' => 2, 'category_id' => 1, 'subcategory_id' => 3, 'game_name' => 'BTD4', 'category_name' => 'Intermediate', 'subcategory_name' => 'Mobile maps'],
        ['game_id' => 2, 'category_id' => 2, 'subcategory_id' => 3, 'game_name' => 'BTD4', 'category_name' => 'Advanced', 'subcategory_name' => 'Mobile maps'],
        ['game_id' => 2, 'category_id' => 3, 'subcategory_id' => 3, 'game_name' => 'BTD4', 'category_name' => 'Expert', 'subcategory_name' => 'Mobile maps'],
        ['game_id' => 2, 'category_id' => 4, 'subcategory_id' => 3, 'game_name' => 'BTD4', 'category_name' => 'Extreme', 'subcategory_name' => 'Mobile maps'],
        ['game_id' => 2, 'category_id' => 0, 'subcategory_id' => 4, 'game_name' => 'BTD4', 'category_name' => 'Beginner', 'subcategory_name' => 'Scrapped maps'],
        ['game_id' => 3, 'category_id' => 0, 'subcategory_id' => 0, 'game_name' => 'BTD5', 'category_name' => 'Beginner', 'subcategory_name' => 'General maps'],
        ['game_id' => 3, 'category_id' => 1, 'subcategory_id' => 0, 'game_name' => 'BTD5', 'category_name' => 'Intermediate', 'subcategory_name' => 'General maps'],
        ['game_id' => 3, 'category_id' => 2, 'subcategory_id' => 0, 'game_name' => 'BTD5', 'category_name' => 'Advanced', 'subcategory_name' => 'General maps'],
        ['game_id' => 3, 'category_id' => 3, 'subcategory_id' => 0, 'game_name' => 'BTD5', 'category_name' => 'Expert', 'subcategory_name' => 'General maps'],
        ['game_id' => 3, 'category_id' => 4, 'subcategory_id' => 0, 'game_name' => 'BTD5', 'category_name' => 'Extreme', 'subcategory_name' => 'General maps'],
        ['game_id' => 3, 'category_id' => 0, 'subcategory_id' => 1, 'game_name' => 'BTD5', 'category_name' => 'Beginner', 'subcategory_name' => 'Deluxe maps'],
        ['game_id' => 3, 'category_id' => 1, 'subcategory_id' => 1, 'game_name' => 'BTD5', 'category_name' => 'Intermediate', 'subcategory_name' => 'Deluxe maps'],
        ['game_id' => 3, 'category_id' => 2, 'subcategory_id' => 1, 'game_name' => 'BTD5', 'category_name' => 'Advanced', 'subcategory_name' => 'Deluxe maps'],
        ['game_id' => 3, 'category_id' => 3, 'subcategory_id' => 1, 'game_name' => 'BTD5', 'category_name' => 'Expert', 'subcategory_name' => 'Deluxe maps'],
        ['game_id' => 3, 'category_id' => 0, 'subcategory_id' => 2, 'game_name' => 'BTD5', 'category_name' => 'Beginner', 'subcategory_name' => 'Special Mission maps'],
        ['game_id' => 3, 'category_id' => 4, 'subcategory_id' => 2, 'game_name' => 'BTD5', 'category_name' => 'Extreme', 'subcategory_name' => 'Special Mission maps'],
        ['game_id' => 3, 'category_id' => 0, 'subcategory_id' => 3, 'game_name' => 'BTD5', 'category_name' => 'Beginner', 'subcategory_name' => 'Mobile/Steam maps'],
        ['game_id' => 3, 'category_id' => 1, 'subcategory_id' => 3, 'game_name' => 'BTD5', 'category_name' => 'Intermediate', 'subcategory_name' => 'Mobile/Steam maps'],
        ['game_id' => 3, 'category_id' => 2, 'subcategory_id' => 3, 'game_name' => 'BTD5', 'category_name' => 'Advanced', 'subcategory_name' => 'Mobile/Steam maps'],
        ['game_id' => 3, 'category_id' => 3, 'subcategory_id' => 3, 'game_name' => 'BTD5', 'category_name' => 'Expert', 'subcategory_name' => 'Mobile/Steam maps'],
        ['game_id' => 3, 'category_id' => 4, 'subcategory_id' => 3, 'game_name' => 'BTD5', 'category_name' => 'Extreme', 'subcategory_name' => 'Mobile/Steam maps'],
        ['game_id' => 3, 'category_id' => 0, 'subcategory_id' => 4, 'game_name' => 'BTD5', 'category_name' => 'Beginner', 'subcategory_name' => 'Mobile/Steam Event Exclusives'],
        ['game_id' => 3, 'category_id' => 0, 'subcategory_id' => 5, 'game_name' => 'BTD5', 'category_name' => 'Beginner', 'subcategory_name' => 'Scrapped maps'],
        ['game_id' => 3, 'category_id' => 1, 'subcategory_id' => 5, 'game_name' => 'BTD5', 'category_name' => 'Intermediate', 'subcategory_name' => 'Scrapped maps'],
        ['game_id' => 4, 'category_id' => 0, 'subcategory_id' => 0, 'game_name' => 'BTDB1', 'category_name' => 'General maps', 'subcategory_name' => null],
        ['game_id' => 4, 'category_id' => 1, 'subcategory_id' => 0, 'game_name' => 'BTDB1', 'category_name' => 'Mobile/Steam maps', 'subcategory_name' => null],
        ['game_id' => 4, 'category_id' => 2, 'subcategory_id' => 0, 'game_name' => 'BTDB1', 'category_name' => 'Club maps', 'subcategory_name' => null],
        ['game_id' => 4, 'category_id' => 3, 'subcategory_id' => 0, 'game_name' => 'BTDB1', 'category_name' => 'Boss arena maps', 'subcategory_name' => null],
        ['game_id' => 4, 'category_id' => 4, 'subcategory_id' => 0, 'game_name' => 'BTDB1', 'category_name' => 'Winter maps', 'subcategory_name' => null],
        ['game_id' => 5, 'category_id' => 0, 'subcategory_id' => 0, 'game_name' => 'BMC', 'category_name' => 'Grass tiles', 'subcategory_name' => null],
        ['game_id' => 5, 'category_id' => 1, 'subcategory_id' => 0, 'game_name' => 'BMC', 'category_name' => 'Forest tiles', 'subcategory_name' => null],
        ['game_id' => 5, 'category_id' => 2, 'subcategory_id' => 0, 'game_name' => 'BMC', 'category_name' => 'Heavy Forest tiles', 'subcategory_name' => null],
        ['game_id' => 5, 'category_id' => 3, 'subcategory_id' => 0, 'game_name' => 'BMC', 'category_name' => 'Jungle tiles', 'subcategory_name' => null],
        ['game_id' => 5, 'category_id' => 4, 'subcategory_id' => 0, 'game_name' => 'BMC', 'category_name' => 'Desert tiles', 'subcategory_name' => null],
        ['game_id' => 5, 'category_id' => 5, 'subcategory_id' => 0, 'game_name' => 'BMC', 'category_name' => 'Hills tiles', 'subcategory_name' => null],
        ['game_id' => 5, 'category_id' => 6, 'subcategory_id' => 0, 'game_name' => 'BMC', 'category_name' => 'Mountain tiles', 'subcategory_name' => null],
        ['game_id' => 5, 'category_id' => 7, 'subcategory_id' => 0, 'game_name' => 'BMC', 'category_name' => 'River tiles', 'subcategory_name' => null],
        ['game_id' => 5, 'category_id' => 8, 'subcategory_id' => 0, 'game_name' => 'BMC', 'category_name' => 'Lake tiles', 'subcategory_name' => null],
        ['game_id' => 5, 'category_id' => 9, 'subcategory_id' => 0, 'game_name' => 'BMC', 'category_name' => 'Snow tiles', 'subcategory_name' => null],
        ['game_id' => 5, 'category_id' => 10, 'subcategory_id' => 0, 'game_name' => 'BMC', 'category_name' => 'Volcano tiles', 'subcategory_name' => null],
        ['game_id' => 5, 'category_id' => 11, 'subcategory_id' => 0, 'game_name' => 'BMC', 'category_name' => 'Special Mission tiles', 'subcategory_name' => null],
        ['game_id' => 5, 'category_id' => 12, 'subcategory_id' => 0, 'game_name' => 'BMC', 'category_name' => 'Boss maps', 'subcategory_name' => null],
        ['game_id' => 5, 'category_id' => 13, 'subcategory_id' => 0, 'game_name' => 'BMC', 'category_name' => 'High Desert tiles', 'subcategory_name' => null],
        ['game_id' => 5, 'category_id' => 14, 'subcategory_id' => 0, 'game_name' => 'BMC', 'category_name' => 'Badlands tiles', 'subcategory_name' => null],
        ['game_id' => 5, 'category_id' => 15, 'subcategory_id' => 0, 'game_name' => 'BMC', 'category_name' => 'Arid Grassland tiles', 'subcategory_name' => null],
        ['game_id' => 6, 'category_id' => 0, 'subcategory_id' => 0, 'game_name' => 'BATTD', 'category_name' => 'Grasslands world', 'subcategory_name' => null],
        ['game_id' => 6, 'category_id' => 1, 'subcategory_id' => 0, 'game_name' => 'BATTD', 'category_name' => 'Candy Kingdom world', 'subcategory_name' => null],
        ['game_id' => 6, 'category_id' => 2, 'subcategory_id' => 0, 'game_name' => 'BATTD', 'category_name' => 'Ice Kingdom world', 'subcategory_name' => null],
        ['game_id' => 6, 'category_id' => 3, 'subcategory_id' => 0, 'game_name' => 'BATTD', 'category_name' => 'Badlands world', 'subcategory_name' => null],
        ['game_id' => 6, 'category_id' => 4, 'subcategory_id' => 0, 'game_name' => 'BATTD', 'category_name' => 'Fire Kingdom world', 'subcategory_name' => null],
        ['game_id' => 6, 'category_id' => 5, 'subcategory_id' => 0, 'game_name' => 'BATTD', 'category_name' => 'Lemongrab world', 'subcategory_name' => null],
        ['game_id' => 6, 'category_id' => 6, 'subcategory_id' => 0, 'game_name' => 'BATTD', 'category_name' => 'Underwater City world', 'subcategory_name' => null],
        ['game_id' => 6, 'category_id' => 7, 'subcategory_id' => 0, 'game_name' => 'BATTD', 'category_name' => 'Haunted Swamp world', 'subcategory_name' => null],
        ['game_id' => 6, 'category_id' => 8, 'subcategory_id' => 0, 'game_name' => 'BATTD', 'category_name' => 'Lumpy Space world', 'subcategory_name' => null],
        ['game_id' => 7, 'category_id' => 0, 'subcategory_id' => 0, 'game_name' => 'BTDB2/BTD6 Scrapped', 'category_name' => 'BTDB2 Maps', 'subcategory_name' => null],
        ['game_id' => 7, 'category_id' => 1, 'subcategory_id' => 0, 'game_name' => 'BTDB2/BTD6 Scrapped', 'category_name' => 'Scrapped maps', 'subcategory_name' => null],
    ];

    public function run(): void
    {
        foreach (self::$games as $game) {
            RetroGame::firstOrCreate(
                [
                    'game_id' => $game['game_id'],
                    'category_id' => $game['category_id'],
                    'subcategory_id' => $game['subcategory_id'],
                ],
                $game
            );
        }
    }
}
