<?php

namespace Tests\Feature\Maps;

use App\Models\Creator;
use App\Models\Config;
use App\Models\Map;
use App\Models\MapAlias;
use App\Models\MapListMeta;
use App\Models\RetroMap;
use App\Models\User;
use App\Models\Verification;
use App\Services\NinjaKiwi\NinjaKiwiApiClient;
use Tests\Helpers\MapTestHelper;
use Tests\TestCase;

class GetMapTest extends TestCase
{
    #[Group('get')]
    #[Group('maps')]
    public function test_map_preview_url_defaults_to_ninja_kiwi_when_null(): void
    {
        $map = Map::factory()->withMeta()->create(['map_preview_url' => null]);

        $actual = $this->getJson("/api/maps/{$map->code}")
            ->assertStatus(200)
            ->json();

        $expectedUrl = rtrim(config('app.url'), '/') . "/api/proxy/ninjakiwi/maps/{$map->code}/preview.webp";

        $this->assertEquals($expectedUrl, $actual['map_preview_url']);
    }

    #[Group('get')]
    #[Group('maps')]
    public function test_get_map_successfully(): void
    {
        $map = Map::factory()->withMeta()->create();

        $actual = $this->getJson("/api/maps/{$map->code}")
            ->assertStatus(200)
            ->json();

        $meta = MapListMeta::where('code', $map->code)->first();
        $expected = [
            ...MapTestHelper::mergeMapMeta($map, $meta),
            'is_verified' => false,
            'aliases' => [],
            'creators' => [],
            'verifications' => [],
        ];

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('maps')]
    public function test_get_deleted_map_successfully(): void
    {
        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create([
            'deleted_on' => now()->subHour(),
        ]);

        $actual = $this->getJson("/api/maps/{$map->code}")
            ->assertStatus(200)
            ->json();

        $this->assertNotNull($actual['deleted_on']);
        $this->assertEquals($map->code, $actual['code']);
    }

    #[Group('get')]
    #[Group('maps')]
    public function test_get_map_with_no_creators_and_no_verifiers(): void
    {
        $map = Map::factory()->withMeta()->create();

        $actual = $this->getJson("/api/maps/{$map->code}")
            ->assertStatus(200)
            ->json();

        $this->assertEquals([], $actual['creators']);
        $this->assertEquals([], $actual['verifications']);
        $this->assertFalse($actual['is_verified']);
    }

    #[Group('get')]
    #[Group('maps')]
    public function test_get_map_with_verifiers(): void
    {
        $map = Map::factory()->withMeta()->create();
        $verifiers = User::factory()->count(2)->create();

        $verifications = Verification::factory()
            ->count(2)
            ->sequence(fn($seq) => [
                'map_code' => $map->code,
                'user_id' => $verifiers[$seq->index]->discord_id,
                'version' => null,
            ])
            ->create();

        $actual = $this->getJson("/api/maps/{$map->code}")
            ->assertStatus(200)
            ->json();

        // Build expected verifications array with eager-loaded user data
        $expectedVerifications = $verifications->map(fn($v) => Verification::jsonStructure([
            ...$v->toArray(),
            'user' => $v->user->toArray(),
        ]))->toArray();

        $this->assertEquals($expectedVerifications, $actual['verifications']);
        $this->assertTrue($actual['is_verified']);
    }

    #[Group('get')]
    #[Group('maps')]
    public function test_get_map_with_creators(): void
    {
        $map = Map::factory()->withMeta()->create();
        $creators = User::factory()->count(3)->create();

        Creator::factory()
            ->count(3)
            ->sequence(fn($seq) => [
                'map_code' => $map->code,
                'user_id' => $creators[$seq->index]->discord_id,
                'role' => fake()->randomElement(['Gameplay', 'Decoration', 'Both']),
            ])
            ->create();

        $actual = $this->getJson("/api/maps/{$map->code}")
            ->assertStatus(200)
            ->json();

        // Build expected creators array with eager-loaded user data
        $creatorsWithUsers = Creator::where('map_code', $map->code)->with('user')->get();
        $expectedCreators = $creatorsWithUsers
            ->map(fn($c) => Creator::jsonStructure([
                ...$c->toArray(),
                'user' => $c->user->toArray(),
            ]))
            ->toArray();

        $this->assertEquals($expectedCreators, $actual['creators']);
    }

    #[Group('get')]
    #[Group('maps')]
    public function test_get_map_with_remake_of_includes_retro_map_and_game(): void
    {
        $retroMap = RetroMap::factory()->create();
        $map = Map::factory()->withMeta(['remake_of' => $retroMap->id])->create();

        $actual = $this->getJson("/api/maps/{$map->code}")
            ->assertStatus(200)
            ->json();

        $retroMapWithGame = RetroMap::where('id', $retroMap->id)->with('game')->first();
        $expected = RetroMap::jsonStructure([
            ...$retroMapWithGame->toArray(),
        ]);

        $this->assertEquals($expected, $actual['retro_map']);
    }

    #[Group('get')]
    #[Group('maps')]
    public function test_get_map_that_does_not_exist_returns_404(): void
    {
        $this->getJson('/api/maps/NONEXIST')
            ->assertStatus(404)
            ->assertJson(['message' => 'Not Found']);
    }

    #[Group('get')]
    #[Group('maps')]
    public function test_get_map_with_multiple_metas_shows_latest(): void
    {
        $map = Map::factory()->create();

        // Old meta
        MapListMeta::factory()->for($map)->create([
            'placement_curver' => 10,
            'created_on' => now()->subHours(2),
        ]);

        // New meta (should be returned)
        MapListMeta::factory()->for($map)->create([
            'placement_curver' => 25,
            'created_on' => now()->subHour(),
        ]);

        $actual = $this->getJson("/api/maps/{$map->code}")
            ->assertStatus(200)
            ->json();

        $this->assertEquals(25, $actual['placement_curver']);
    }

    #[Group('get')]
    #[Group('maps')]
    public function test_get_map_with_timestamp_before_meta_returns_404(): void
    {
        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create([
            'created_on' => now()->subHour(),
        ]);

        $timestamp = now()->subHours(2)->timestamp;

        $this->getJson("/api/maps/{$map->code}?timestamp={$timestamp}")
            ->assertStatus(404)
            ->assertJson(['message' => 'Not Found']);
    }

    #[Group('get')]
    #[Group('maps')]
    public function test_get_map_with_timestamp_selects_correct_meta(): void
    {
        $map = Map::factory()->create();

        // Old meta
        MapListMeta::factory()->for($map)->create([
            'placement_curver' => 10,
            'created_on' => now()->subHours(3),
        ]);

        // Middle meta (should be selected)
        $middleTime = now()->subHours(2);
        MapListMeta::factory()->for($map)->create([
            'placement_curver' => 25,
            'created_on' => $middleTime,
        ]);

        // New meta
        MapListMeta::factory()->for($map)->create([
            'placement_curver' => 40,
            'created_on' => now()->subHour(),
        ]);

        $timestamp = now()->subHours(1)->subMinutes(30)->timestamp;

        $actual = $this->getJson("/api/maps/{$map->code}?timestamp={$timestamp}")
            ->assertStatus(200)
            ->json();

        $this->assertEquals(25, $actual['placement_curver']);
    }

    #[Group('get')]
    #[Group('maps')]
    public function test_get_map_with_creators_flair_include(): void
    {
        $map = Map::factory()->withMeta()->create();
        $user = User::factory()->create([
            'nk_oak' => 'test_oak_123',
            'cached_avatar_url' => 'https://example.com/avatar.png',
            'cached_banner_url' => 'https://example.com/banner.png',
            'ninjakiwi_cache_expire' => now()->addHour(),
        ]);

        Creator::factory()->create([
            'map_code' => $map->code,
            'user_id' => $user->discord_id,
        ]);

        $actual = $this->getJson("/api/maps/{$map->code}?include=creators.flair")
            ->assertStatus(200)
            ->json();

        $creator = $actual['creators'][0];
        $this->assertEquals($user->discord_id, $creator['user_id']);
        $this->assertEquals('https://example.com/avatar.png', $creator['user']['avatar_url']);
        $this->assertEquals('https://example.com/banner.png', $creator['user']['banner_url']);
    }

    #[Group('get')]
    #[Group('maps')]
    public function test_get_map_with_verifiers_flair_include(): void
    {
        Config::factory()->type('int')->name('current_btd6_ver')->create(['value' => '441']);

        $map = Map::factory()->withMeta()->create();
        $user = User::factory()->create([
            'nk_oak' => 'test_oak_456',
            'cached_avatar_url' => 'https://example.com/verifier_avatar.png',
            'cached_banner_url' => 'https://example.com/verifier_banner.png',
            'ninjakiwi_cache_expire' => now()->addHour(),
        ]);

        Verification::factory()->create([
            'map_code' => $map->code,
            'user_id' => $user->discord_id,
            'version' => null,
        ]);

        $actual = $this->getJson("/api/maps/{$map->code}?include=verifiers.flair")
            ->assertStatus(200)
            ->json();

        $verification = $actual['verifications'][0];
        $this->assertEquals($user->discord_id, $verification['user_id']);
        $this->assertEquals('https://example.com/verifier_avatar.png', $verification['user']['avatar_url']);
        $this->assertEquals('https://example.com/verifier_banner.png', $verification['user']['banner_url']);
    }

    #[Group('get')]
    #[Group('maps')]
    public function test_get_map_with_aliases_returns_sorted_aliases(): void
    {
        $map = Map::factory()->withMeta()->create();

        MapAlias::factory()
            ->count(3)
            ->sequence(
                ['alias' => 'zebra'],
                ['alias' => 'apple'],
                ['alias' => 'banana'],
            )
            ->for($map, 'map')
            ->create();

        $actual = $this->getJson('/api/maps/' . $map->code)
            ->assertStatus(200)
            ->json();

        $this->assertEquals(['apple', 'banana', 'zebra'], $actual['aliases']);
    }
}
