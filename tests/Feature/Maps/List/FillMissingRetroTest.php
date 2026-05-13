<?php

namespace Tests\Feature\Maps\List;

use App\Constants\FormatConstants;
use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\RetroGame;
use App\Models\RetroMap;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FillMissingRetroTest extends TestCase
{
    // GET /maps?fill_missing_retro=true&format_id=11
    // Backfills paginated results with retro maps that have no active remake. Only valid with format_id=11 (Nostalgia Pack).

    protected function setUp(): void
    {
        parent::setUp();
        // Core seeder seeds RetroGames but not RetroMaps — cleanup not needed,
        // but delete any seeded retro data just to be safe.
        DB::table('retro_maps')->delete();
        DB::table('retro_games')->delete();
    }

    private function createRemadeMap(RetroMap $retroMap): Map
    {
        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create([
            'remake_of' => $retroMap->id,
            'created_on' => now()->subMinute(),
            'deleted_on' => null,
        ]);
        return $map;
    }

    private function url(array $params = []): string
    {
        $base = '?format_id=' . FormatConstants::NOSTALGIA_PACK . '&fill_missing_retro=true';
        foreach ($params as $k => $v) {
            $base .= "&{$k}={$v}";
        }
        return '/api/maps' . $base;
    }

    public function test_results_include_unmapped_retro_maps_after_regular_maps(): void
    {
        $game = RetroGame::factory()->create();
        $retroMap1 = RetroMap::factory()->for($game, 'game')->create(); // unremade
        $retroMap2 = RetroMap::factory()->for($game, 'game')->create(); // will be remade
        $remadeMap = $this->createRemadeMap($retroMap2);

        $actual = $this->getJson($this->url())->assertStatus(200)->json('data');

        // Regular map (code != null) comes first, then backfill (code == null)
        $this->assertCount(2, $actual);
        $this->assertEquals($remadeMap->code, $actual[0]['code']);
        $this->assertNull($actual[1]['code']);
        $this->assertEquals($retroMap1->id, $actual[1]['retro_map']['id']);
    }

    public function test_retro_maps_with_active_remake_excluded_from_backfill(): void
    {
        $game = RetroGame::factory()->create();
        $retroMap = RetroMap::factory()->for($game, 'game')->create();
        $this->createRemadeMap($retroMap);

        $actual = $this->getJson($this->url())->assertStatus(200)->json('data');

        // Only the regular map appears; retroMap is not in backfill
        $this->assertCount(1, $actual);
        $this->assertNotNull($actual[0]['code']); // regular map
    }

    public function test_format_subfilter_game_id_applied_to_backfill_retro_maps(): void
    {
        $game1 = RetroGame::factory()->create();
        $game2 = RetroGame::factory()->create();
        RetroMap::factory()->for($game1, 'game')->create(['name' => 'Game1 Map']);
        RetroMap::factory()->for($game2, 'game')->create(['name' => 'Game2 Map']);

        $actual = $this->getJson($this->url(['format_subfilter' => $game1->game_id]))
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(1, $actual);
        $this->assertEquals('Game1 Map', $actual[0]['name']);
    }

    public function test_pagination_meta_accounts_for_backfilled_entries(): void
    {
        $game = RetroGame::factory()->create();
        RetroMap::factory()->count(3)->for($game, 'game')->create();

        $actual = $this->getJson($this->url())
            ->assertStatus(200)
            ->json('meta');

        $this->assertEquals(3, $actual['total']);
    }

    public function test_page_2_offset_correctly_calculated_skips_already_shown_remade_maps(): void
    {
        $game = RetroGame::factory()->create();
        // Create 3 retro maps ordered by sort_order
        $retro1 = RetroMap::factory()->for($game, 'game')->create(['sort_order' => 1]);
        $retro2 = RetroMap::factory()->for($game, 'game')->create(['sort_order' => 2]);
        $retro3 = RetroMap::factory()->for($game, 'game')->create(['sort_order' => 3]);

        $page1 = $this->getJson($this->url(['per_page' => 2, 'page' => 1]))
            ->assertStatus(200)
            ->json('data');
        $page2 = $this->getJson($this->url(['per_page' => 2, 'page' => 2]))
            ->assertStatus(200)
            ->json('data');

        // Page 1 has first 2 retro maps, page 2 has the 3rd
        $this->assertCount(2, $page1);
        $this->assertCount(1, $page2);

        $page1RetroIds = collect($page1)->pluck('retro_map.id')->toArray();
        $page2RetroIds = collect($page2)->pluck('retro_map.id')->toArray();

        // No overlap between pages
        $this->assertEmpty(array_intersect($page1RetroIds, $page2RetroIds));
        $this->assertContains($retro3->id, $page2RetroIds);
    }

    public function test_fill_missing_retro_true_without_format_id_11_returns_422(): void
    {
        $this->getJson('/api/maps?fill_missing_retro=true')
            ->assertStatus(422);
    }

    public function test_fill_missing_retro_true_with_format_id_1_returns_422(): void
    {
        $this->getJson('/api/maps?fill_missing_retro=true&format_id=' . FormatConstants::MAPLIST)
            ->assertStatus(422);
    }

    public function test_fill_missing_retro_false_with_format_id_11_returns_normal_results(): void
    {
        $game = RetroGame::factory()->create();
        RetroMap::factory()->for($game, 'game')->create();

        $actual = $this->getJson('/api/maps?format_id=' . FormatConstants::NOSTALGIA_PACK . '&fill_missing_retro=false')
            ->assertStatus(200)
            ->json('data');

        // No backfill — no regular maps (none have remake_of set)
        $this->assertEmpty($actual);
    }

    public function test_no_unremade_retro_maps_exist_backfill_adds_nothing(): void
    {
        $game = RetroGame::factory()->create();
        $retroMap = RetroMap::factory()->for($game, 'game')->create();
        $remadeMap = $this->createRemadeMap($retroMap);

        $actual = $this->getJson($this->url())->assertStatus(200)->json('data');

        // Only the regular remade map; no backfill entries
        $this->assertCount(1, $actual);
        $this->assertEquals($remadeMap->code, $actual[0]['code']);
    }

    public function test_all_retro_maps_have_remakes_backfill_adds_nothing(): void
    {
        $game = RetroGame::factory()->create();
        $retro1 = RetroMap::factory()->for($game, 'game')->create();
        $retro2 = RetroMap::factory()->for($game, 'game')->create();
        $this->createRemadeMap($retro1);
        $this->createRemadeMap($retro2);

        $actual = $this->getJson($this->url())->assertStatus(200)->json('data');

        // Both regular maps appear, no backfill
        $this->assertCount(2, $actual);
        foreach ($actual as $item) {
            $this->assertNotNull($item['code']);
        }
    }

    public function test_deleted_retro_maps_excluded_from_backfill(): void
    {
        $game = RetroGame::factory()->create();
        $retroMap = RetroMap::factory()->for($game, 'game')->create();
        $retroMap->delete(); // soft-delete

        $actual = $this->getJson($this->url())->assertStatus(200)->json('data');

        $this->assertEmpty($actual);
    }
}
