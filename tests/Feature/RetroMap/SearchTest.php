<?php

namespace Tests\Feature\RetroMap;

use App\Models\RetroGame;
use App\Models\RetroMap;
use Tests\TestCase;

class SearchTest extends TestCase
{
    // GET /api/maps/retro?search=... — ILIKE search on retro map names.

    public function test_exact_name_match_returns_the_map(): void
    {
        $game = RetroGame::factory()->create();
        RetroMap::factory()->for($game, 'game')->create(['name' => 'Monkey Meadow']);

        $actual = $this->getJson('/api/maps/retro?search=Monkey Meadow')
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(1, $actual);
        $this->assertEquals('Monkey Meadow', $actual[0]['name']);
    }

    public function test_partial_name_match_returns_the_map(): void
    {
        $game = RetroGame::factory()->create();
        RetroMap::factory()->for($game, 'game')->create(['name' => 'Monkey Meadow']);

        $actual = $this->getJson('/api/maps/retro?search=Meadow')
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(1, $actual);
        $this->assertEquals('Monkey Meadow', $actual[0]['name']);
    }

    public function test_search_is_case_insensitive(): void
    {
        $game = RetroGame::factory()->create();
        RetroMap::factory()->for($game, 'game')->create(['name' => 'Monkey Meadow']);

        $actual = $this->getJson('/api/maps/retro?search=monkey meadow')
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(1, $actual);
        $this->assertEquals('Monkey Meadow', $actual[0]['name']);
    }

    public function test_search_with_no_matches_returns_empty_data_not_an_error(): void
    {
        $game = RetroGame::factory()->create();
        RetroMap::factory()->for($game, 'game')->create(['name' => 'Monkey Meadow']);

        $actual = $this->getJson('/api/maps/retro?search=xyznotexist99999')
            ->assertStatus(200)
            ->json('data');

        $this->assertEmpty($actual);
    }

    public function test_search_over_255_characters_returns_422(): void
    {
        $this->getJson('/api/maps/retro?search=' . str_repeat('a', 256))
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('search');
    }

    public function test_empty_search_string_returns_all_results(): void
    {
        $game = RetroGame::factory()->create();
        RetroMap::factory()->count(3)->for($game, 'game')->create();

        $actual = $this->getJson('/api/maps/retro?search=')
            ->assertStatus(200)
            ->json('meta.total');

        $this->assertEquals(3, $actual);
    }

    public function test_deleted_maps_excluded_from_search_results(): void
    {
        $game = RetroGame::factory()->create();
        $map = RetroMap::factory()->for($game, 'game')->create(['name' => 'Lost Map Unique99']);
        $map->delete();

        $actual = $this->getJson('/api/maps/retro?search=Lost Map Unique99')
            ->assertStatus(200)
            ->json('data');

        $this->assertEmpty($actual);
    }

    public function test_search_combined_with_other_filters_both_applied(): void
    {
        $game1 = RetroGame::factory()->create();
        $game2 = RetroGame::factory()->create();

        RetroMap::factory()->for($game1, 'game')->create(['name' => 'Jungle Jump']);
        RetroMap::factory()->for($game2, 'game')->create(['name' => 'Jungle Trek']);

        $actual = $this->getJson("/api/maps/retro?search=Jungle&game_id={$game1->game_id}")
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(1, $actual);
        $this->assertEquals('Jungle Jump', $actual[0]['name']);
    }
}
