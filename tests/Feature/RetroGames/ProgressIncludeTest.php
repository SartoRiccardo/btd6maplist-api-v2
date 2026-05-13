<?php

namespace Tests\Feature\RetroGames;

use App\Models\MapListMeta;
use App\Models\RetroGame;
use App\Models\RetroMap;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProgressIncludeTest extends TestCase
{
    // GET /retro-games?include[]=progress
    // Adds total_maps and maps_remade to each retro game in the response.

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('retro_maps')->delete();
        DB::table('retro_games')->delete();
    }

    private function makeRemake(RetroMap $retroMap): MapListMeta
    {
        return MapListMeta::factory()->create([
            'remake_of' => $retroMap->id,
            'created_on' => now()->subMinute(),
            'deleted_on' => null,
        ]);
    }

    private function getGameProgress(int $gameId): array
    {
        $data = $this->getJson('/api/retro-games?include[]=progress')
            ->assertStatus(200)
            ->json('data');

        return collect($data)->firstWhere('id', $gameId) ?? [];
    }

    public function test_game_with_maps_and_some_remade_both_counts_correct(): void
    {
        $game = RetroGame::factory()->create();
        $maps = RetroMap::factory()->count(3)->for($game, 'game')->create();
        $this->makeRemake($maps[0]);
        $this->makeRemake($maps[1]);

        $gameData = $this->getGameProgress($game->id);

        $this->assertEquals(3, $gameData['total_maps']);
        $this->assertEquals(2, $gameData['maps_remade']);
    }

    public function test_maps_remade_count_matches_maps_with_active_remake(): void
    {
        $game = RetroGame::factory()->create();
        $map = RetroMap::factory()->for($game, 'game')->create();

        // Active remake
        $this->makeRemake($map);

        $gameData = $this->getGameProgress($game->id);
        $this->assertEquals(1, $gameData['maps_remade']);
    }

    public function test_total_maps_reflects_all_non_deleted_retro_maps_for_that_game(): void
    {
        $game = RetroGame::factory()->create();
        $maps = RetroMap::factory()->count(5)->for($game, 'game')->create();
        $maps[4]->delete(); // soft-delete one

        $gameData = $this->getGameProgress($game->id);
        $this->assertEquals(4, $gameData['total_maps']);
    }

    public function test_game_with_zero_retro_maps_returns_zero_not_null_or_missing(): void
    {
        $game = RetroGame::factory()->create();

        $gameData = $this->getGameProgress($game->id);

        $this->assertArrayHasKey('total_maps', $gameData);
        $this->assertArrayHasKey('maps_remade', $gameData);
        $this->assertEquals(0, $gameData['total_maps']);
        $this->assertEquals(0, $gameData['maps_remade']);
    }

    public function test_deleted_retro_maps_not_counted_in_total_maps(): void
    {
        $game = RetroGame::factory()->create();
        $map = RetroMap::factory()->for($game, 'game')->create();
        $map->delete();

        $gameData = $this->getGameProgress($game->id);
        $this->assertEquals(0, $gameData['total_maps']);
    }

    public function test_retro_map_with_deleted_remake_does_not_count_toward_maps_remade(): void
    {
        $game = RetroGame::factory()->create();
        $retroMap = RetroMap::factory()->for($game, 'game')->create();

        // Create a remake but with deleted_on set (deleted remake)
        MapListMeta::factory()->create([
            'remake_of' => $retroMap->id,
            'created_on' => now()->subMinute(),
            'deleted_on' => now()->subSeconds(30),
        ]);

        $gameData = $this->getGameProgress($game->id);
        $this->assertEquals(1, $gameData['total_maps']);
        $this->assertEquals(0, $gameData['maps_remade']);
    }

    public function test_maps_remade_never_exceeds_total_maps(): void
    {
        $game = RetroGame::factory()->create();
        $maps = RetroMap::factory()->count(3)->for($game, 'game')->create();
        foreach ($maps as $map) {
            $this->makeRemake($map);
        }

        $gameData = $this->getGameProgress($game->id);
        $this->assertLessThanOrEqual($gameData['total_maps'], $gameData['maps_remade']);
    }

    public function test_include_absent_total_maps_and_maps_remade_not_present_in_response(): void
    {
        $game = RetroGame::factory()->create();

        $data = $this->getJson('/api/retro-games')
            ->assertStatus(200)
            ->json('data');

        $gameData = collect($data)->firstWhere('id', $game->id);
        $this->assertArrayNotHasKey('total_maps', $gameData);
        $this->assertArrayNotHasKey('maps_remade', $gameData);
    }

    public function test_include_progress_with_unknown_second_value_returns_422(): void
    {
        // Validation is strict: only 'progress' is a valid include value.
        // Unknown values cause 422, not silent ignore.
        $this->getJson('/api/retro-games?include[]=progress&include[]=garbage')
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('include.1');
    }
}
