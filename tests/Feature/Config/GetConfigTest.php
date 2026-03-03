<?php

namespace Tests\Feature\Config;

use App\Models\Config;
use Tests\TestCase;
use PHPUnit\Metadata\Group;

class GetConfigTest extends TestCase
{
    #[Group('config')]
    public function test_get_config_returns_kv_object_with_all_configs(): void
    {
        Config::factory()->create(['name' => 'map_count', 'value' => '100', 'type' => 'int']);
        Config::factory()->create(['name' => 'points_multiplier', 'value' => '1.5', 'type' => 'float']);
        Config::factory()->create(['name' => 'site_title', 'value' => 'BTD6 Maplist', 'type' => 'string']);

        $this->getJson('/api/config')
            ->assertStatus(200)
            ->assertJson([
                'map_count' => 100,
                'points_multiplier' => 1.5,
                'site_title' => 'BTD6 Maplist',
            ]);
    }

    #[Group('config')]
    public function test_get_config_returns_empty_object_if_no_configs_exist(): void
    {
        Config::query()->delete(); // Ensure no configs

        $response = $this->getJson('/api/config');

        $response->assertStatus(200)
            ->assertJson([]);
    }
}
