<?php

namespace Tests\Feature\Maps\List;

use App\Constants\FormatConstants;
use App\Models\Map;
use App\Models\MapListMeta;
use Tests\TestCase;

class SortByTest extends TestCase
{
    // GET /maps?sort_by=...
    // Overrides the default format sort. Valid values: placement_curver, placement_allver, difficulty, botb_difficulty, created_on. NULLs go last.

    public function test_sort_by_placement_curver_returns_maps_in_ascending_order(): void
    {
        $maps = Map::factory()->count(3)->create();
        MapListMeta::factory()->for($maps[0])->create(['placement_curver' => 3, 'created_on' => now()->subSeconds(3)]);
        MapListMeta::factory()->for($maps[1])->create(['placement_curver' => 1, 'created_on' => now()->subSeconds(2)]);
        MapListMeta::factory()->for($maps[2])->create(['placement_curver' => 2, 'created_on' => now()->subSeconds(1)]);

        $actual = $this->getJson('/api/maps?format_id=' . FormatConstants::MAPLIST . '&sort_by=placement_curver')
            ->assertStatus(200)
            ->json('data');

        $placements = collect($actual)->pluck('placement_curver')->values()->toArray();
        $this->assertEquals([1, 2, 3], $placements);
    }

    public function test_sort_by_placement_allver_returns_maps_in_ascending_order(): void
    {
        $maps = Map::factory()->count(3)->create();
        MapListMeta::factory()->for($maps[0])->create(['placement_allver' => 3, 'created_on' => now()->subSeconds(3)]);
        MapListMeta::factory()->for($maps[1])->create(['placement_allver' => 1, 'created_on' => now()->subSeconds(2)]);
        MapListMeta::factory()->for($maps[2])->create(['placement_allver' => 2, 'created_on' => now()->subSeconds(1)]);

        $actual = $this->getJson('/api/maps?format_id=' . FormatConstants::MAPLIST_ALL_VERSIONS . '&sort_by=placement_allver')
            ->assertStatus(200)
            ->json('data');

        $placements = collect($actual)->pluck('placement_allver')->values()->toArray();
        $this->assertEquals([1, 2, 3], $placements);
    }

    public function test_sort_by_difficulty_returns_maps_in_ascending_order(): void
    {
        $maps = Map::factory()->count(3)->create();
        MapListMeta::factory()->for($maps[0])->create(['difficulty' => 3, 'created_on' => now()->subSeconds(3)]);
        MapListMeta::factory()->for($maps[1])->create(['difficulty' => 1, 'created_on' => now()->subSeconds(2)]);
        MapListMeta::factory()->for($maps[2])->create(['difficulty' => 2, 'created_on' => now()->subSeconds(1)]);

        $actual = $this->getJson('/api/maps?format_id=' . FormatConstants::EXPERT_LIST . '&sort_by=difficulty')
            ->assertStatus(200)
            ->json('data');

        $difficulties = collect($actual)->pluck('difficulty')->values()->toArray();
        $this->assertEquals([1, 2, 3], $difficulties);
    }

    public function test_sort_by_botb_difficulty_returns_maps_in_ascending_order(): void
    {
        $maps = Map::factory()->count(3)->create();
        MapListMeta::factory()->for($maps[0])->create(['botb_difficulty' => 3, 'created_on' => now()->subSeconds(3)]);
        MapListMeta::factory()->for($maps[1])->create(['botb_difficulty' => 1, 'created_on' => now()->subSeconds(2)]);
        MapListMeta::factory()->for($maps[2])->create(['botb_difficulty' => 2, 'created_on' => now()->subSeconds(1)]);

        $actual = $this->getJson('/api/maps?format_id=' . FormatConstants::BEST_OF_THE_BEST . '&sort_by=botb_difficulty')
            ->assertStatus(200)
            ->json('data');

        $difficulties = collect($actual)->pluck('botb_difficulty')->values()->toArray();
        $this->assertEquals([1, 2, 3], $difficulties);
    }

    public function test_sort_by_created_on_returns_maps_oldest_first(): void
    {
        $maps = Map::factory()->count(3)->create();
        MapListMeta::factory()->for($maps[0])->create(['placement_curver' => 1, 'created_on' => now()->subSeconds(1)]);
        MapListMeta::factory()->for($maps[1])->create(['placement_curver' => 2, 'created_on' => now()->subSeconds(3)]);
        MapListMeta::factory()->for($maps[2])->create(['placement_curver' => 3, 'created_on' => now()->subSeconds(2)]);

        $actual = $this->getJson('/api/maps?format_id=' . FormatConstants::MAPLIST . '&sort_by=created_on')
            ->assertStatus(200)
            ->json('data');

        $codes = collect($actual)->pluck('code')->values()->toArray();
        $this->assertEquals([$maps[1]->code, $maps[2]->code, $maps[0]->code], $codes);
    }

    public function test_secondary_sort_by_created_on_when_primary_values_are_equal(): void
    {
        $maps = Map::factory()->count(2)->create();
        MapListMeta::factory()->for($maps[0])->create(['placement_curver' => 5, 'created_on' => now()->subSeconds(2)]);
        MapListMeta::factory()->for($maps[1])->create(['placement_curver' => 5, 'created_on' => now()->subSeconds(4)]);

        $actual = $this->getJson('/api/maps?format_id=' . FormatConstants::MAPLIST . '&sort_by=placement_curver')
            ->assertStatus(200)
            ->json('data');

        // Both have placement=5; older one (maps[1]) should come first
        $this->assertEquals($maps[1]->code, $actual[0]['code']);
        $this->assertEquals($maps[0]->code, $actual[1]['code']);
    }

    public function test_invalid_sort_by_value_returns_422(): void
    {
        $this->getJson('/api/maps?sort_by=notafield')
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('sort_by');
    }

    public function test_maps_with_null_in_sort_column_appear_last(): void
    {
        $maps = Map::factory()->count(3)->create();
        // No format_id filter so all maps are returned
        MapListMeta::factory()->for($maps[0])->create(['botb_difficulty' => 5, 'created_on' => now()->subSeconds(3)]);
        MapListMeta::factory()->for($maps[1])->create(['botb_difficulty' => null, 'created_on' => now()->subSeconds(2)]);
        MapListMeta::factory()->for($maps[2])->create(['botb_difficulty' => 2, 'created_on' => now()->subSeconds(1)]);

        $actual = $this->getJson('/api/maps?sort_by=botb_difficulty')
            ->assertStatus(200)
            ->json('data');

        $codes = collect($actual)->pluck('code')->values()->toArray();
        $nullMapIdx = array_search($maps[1]->code, $codes);
        $val2MapIdx = array_search($maps[2]->code, $codes);
        $val5MapIdx = array_search($maps[0]->code, $codes);

        // Non-null values appear before null
        $this->assertLessThan($nullMapIdx, $val2MapIdx);
        $this->assertLessThan($nullMapIdx, $val5MapIdx);
    }

    public function test_all_maps_have_null_for_sort_column_all_appear_stable_by_created_on(): void
    {
        $maps = Map::factory()->count(3)->create();
        MapListMeta::factory()->for($maps[0])->create(['botb_difficulty' => null, 'created_on' => now()->subSeconds(1)]);
        MapListMeta::factory()->for($maps[1])->create(['botb_difficulty' => null, 'created_on' => now()->subSeconds(3)]);
        MapListMeta::factory()->for($maps[2])->create(['botb_difficulty' => null, 'created_on' => now()->subSeconds(2)]);

        $actual = $this->getJson('/api/maps?sort_by=botb_difficulty')
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(3, $actual);
        // All null → order by created_on asc: maps[1] (oldest), maps[2], maps[0]
        $codes = collect($actual)->pluck('code')->values()->toArray();
        $this->assertEquals([$maps[1]->code, $maps[2]->code, $maps[0]->code], $codes);
    }

    public function test_sort_by_overrides_default_format_sort(): void
    {
        // Default sort for MAPLIST is placement_curver.
        // Create maps where placement_curver order != difficulty order.
        $maps = Map::factory()->count(3)->create();
        // placement_curver: 1,2,3 ; difficulty: 3,1,2
        MapListMeta::factory()->for($maps[0])->create(['placement_curver' => 1, 'difficulty' => 3, 'created_on' => now()->subSeconds(3)]);
        MapListMeta::factory()->for($maps[1])->create(['placement_curver' => 2, 'difficulty' => 1, 'created_on' => now()->subSeconds(2)]);
        MapListMeta::factory()->for($maps[2])->create(['placement_curver' => 3, 'difficulty' => 2, 'created_on' => now()->subSeconds(1)]);

        // With sort_by=difficulty, expect order by difficulty: maps[1](1), maps[2](2), maps[0](3)
        $actual = $this->getJson('/api/maps?format_id=' . FormatConstants::MAPLIST . '&sort_by=difficulty')
            ->assertStatus(200)
            ->json('data');

        $codes = collect($actual)->pluck('code')->values()->toArray();
        $this->assertEquals([$maps[1]->code, $maps[2]->code, $maps[0]->code], $codes);
    }
}
