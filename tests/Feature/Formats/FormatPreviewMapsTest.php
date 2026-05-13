<?php

namespace Tests\Feature\Formats;

use App\Constants\FormatConstants;
use App\Models\Format;
use App\Models\Map;
use App\Models\MapListMeta;
use Tests\TestCase;

class FormatPreviewMapsTest extends TestCase
{
    // GET /formats — Preview Maps
    // Each format now eager-loads three preview maps (preview_map_1_code, preview_map_2_code, preview_map_3_code).

    protected function setUp(): void
    {
        parent::setUp();
        // Core seeder sets preview map codes on format 1; clear them so tests start clean.
        Format::where('id', FormatConstants::MAPLIST)->update([
            'preview_map_1_code' => null,
            'preview_map_2_code' => null,
            'preview_map_3_code' => null,
        ]);
    }

    public function test_format_with_all_three_preview_maps_returns_them_in_response(): void
    {
        $maps = Map::factory()->count(3)->create();
        foreach ($maps as $map) {
            MapListMeta::factory()->for($map)->create(['placement_curver' => 1]);
        }

        Format::where('id', FormatConstants::MAPLIST)->update([
            'preview_map_1_code' => $maps[0]->code,
            'preview_map_2_code' => $maps[1]->code,
            'preview_map_3_code' => $maps[2]->code,
        ]);

        $format = $this->getJson('/api/formats/' . FormatConstants::MAPLIST)
            ->assertStatus(200)
            ->json();

        $this->assertNotNull($format['preview_map1']);
        $this->assertNotNull($format['preview_map2']);
        $this->assertNotNull($format['preview_map3']);
    }

    public function test_preview_map_objects_include_the_maps_data(): void
    {
        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create(['placement_curver' => 1]);

        Format::where('id', FormatConstants::MAPLIST)->update([
            'preview_map_1_code' => $map->code,
        ]);

        $format = $this->getJson('/api/formats/' . FormatConstants::MAPLIST)
            ->assertStatus(200)
            ->json();

        $this->assertEquals($map->code, $format['preview_map1']['code']);
        $this->assertEquals($map->name, $format['preview_map1']['name']);
    }

    public function test_format_with_no_preview_maps_returns_null_for_each_preview_map_field(): void
    {
        $format = $this->getJson('/api/formats/' . FormatConstants::MAPLIST)
            ->assertStatus(200)
            ->json();

        $this->assertNull($format['preview_map1']);
        $this->assertNull($format['preview_map2']);
        $this->assertNull($format['preview_map3']);
    }

    public function test_format_with_only_one_preview_map_others_are_null(): void
    {
        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create(['placement_curver' => 1]);

        Format::where('id', FormatConstants::MAPLIST)->update([
            'preview_map_1_code' => $map->code,
        ]);

        $format = $this->getJson('/api/formats/' . FormatConstants::MAPLIST)
            ->assertStatus(200)
            ->json();

        $this->assertNotNull($format['preview_map1']);
        $this->assertNull($format['preview_map2']);
        $this->assertNull($format['preview_map3']);
    }

    public function test_preview_map_code_pointing_to_nonexistent_map_handled_gracefully(): void
    {
        // Bypass validation by updating the DB directly with a non-existent code
        Format::where('id', FormatConstants::MAPLIST)->update([
            'preview_map_1_code' => 'XDOESNOTEXIST',
        ]);

        $format = $this->getJson('/api/formats/' . FormatConstants::MAPLIST)
            ->assertStatus(200)
            ->json();

        // belongsTo returns null when the related record doesn't exist
        $this->assertNull($format['preview_map1']);
    }
}
