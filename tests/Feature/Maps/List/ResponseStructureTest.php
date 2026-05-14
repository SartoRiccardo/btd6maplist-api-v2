<?php

namespace Tests\Feature\Maps\List;

use App\Constants\FormatConstants;
use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\RetroMap;
use Tests\TestCase;

class ResponseStructureTest extends TestCase
{
    #[Group('get')]
    #[Group('maps')]
    #[Group('response')]
    public function test_map_preview_url_defaults_to_ninja_kiwi_when_null(): void
    {
        $map = Map::factory()->withMeta()->create(['map_preview_url' => null]);

        $actual = $this->getJson('/api/maps')
            ->assertStatus(200)
            ->json();

        $expectedUrl = url("/api/proxy/ninjakiwi/maps/{$map->code}/preview.webp");

        $this->assertEquals($expectedUrl, $actual['data'][0]['map_preview_url']);
    }
}
