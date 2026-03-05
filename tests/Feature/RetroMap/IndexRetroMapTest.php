<?php

namespace Tests\Feature\RetroMap;

use App\Models\RetroGame;
use App\Models\RetroMap;
use Tests\TestCase;

class IndexRetroMapTest extends TestCase
{
    #[Group('index')]
    #[Group('retro_maps')]
    public function test_index_returns_standard_paginated_structure(): void
    {
        RetroMap::factory()->count(20)->create();

        $response = $this->getJson('/api/maps/retro?page=1&per_page=15')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);

        $data = $response->json('data');
        $meta = $response->json('meta');

        $this->assertCount(15, $data);
        $this->assertEquals(1, $meta['current_page']);
        $this->assertEquals(15, $meta['per_page']);
        $this->assertEquals(20, $meta['total']);
    }

    #[Group('index')]
    #[Group('retro_maps')]
    public function test_index_returns_exact_structure(): void
    {
        $retroGame = RetroGame::factory()->create();
        $retroMap = RetroMap::factory()->for($retroGame, 'game')->create();

        $actual = $this->getJson('/api/maps/retro')
            ->assertStatus(200)
            ->json('data');

        $expected = [
            RetroMap::jsonStructure([
                ...$retroMap->toArray(),
                'game' => $retroGame->toArray(),
            ], exclude: ['deleted_at']),
        ];

        $this->assertEquals($expected, $this->except($actual, ['*.deleted_at']));
    }

    #[Group('index')]
    #[Group('retro_maps')]
    public function test_index_includes_game_relation_by_default(): void
    {
        RetroMap::factory()->for(RetroGame::factory(), 'game')->create();

        $response = $this->getJson('/api/maps/retro')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'sort_order',
                        'preview_url',
                        'retro_game_id',
                        'game',
                    ],
                ],
            ]);

        $data = $response->json('data');
        $firstMap = $data[0];

        $this->assertIsArray($firstMap['game']);
        $this->assertArrayHasKey('game_id', $firstMap['game']);
    }

    #[Group('index')]
    #[Group('retro_maps')]
    public function test_index_filters_by_external_game_id(): void
    {
        $game1 = RetroGame::factory()->create(['game_id' => 100]);
        $game2 = RetroGame::factory()->create(['game_id' => 200]);

        RetroMap::factory()->for($game1, 'game')->count(3)->create();
        RetroMap::factory()->for($game2, 'game')->count(5)->create();

        $response = $this->getJson('/api/maps/retro?game_id=100')
            ->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(3, $data);
    }

    #[Group('index')]
    #[Group('retro_maps')]
    public function test_index_filters_by_category_id(): void
    {
        $game1 = RetroGame::factory()->create(['category_id' => 10]);
        $game2 = RetroGame::factory()->create(['category_id' => 20]);

        RetroMap::factory()->for($game1, 'game')->count(2)->create();
        RetroMap::factory()->for($game2, 'game')->count(4)->create();

        $response = $this->getJson('/api/maps/retro?category_id=10')
            ->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(2, $data);
    }

    #[Group('index')]
    #[Group('retro_maps')]
    public function test_index_handles_pagination_parameters(): void
    {
        RetroMap::factory()->count(25)->create();

        $response = $this->getJson('/api/maps/retro?page=2&per_page=10')
            ->assertStatus(200);

        $meta = $response->json('meta');

        $this->assertEquals(2, $meta['current_page']);
        $this->assertEquals(10, $meta['per_page']);
        $this->assertCount(10, $response->json('data'));
    }

    #[Group('index')]
    #[Group('retro_maps')]
    public function test_index_returns_empty_data_for_non_existent_filters(): void
    {
        RetroMap::factory()->count(5)->create();

        $response = $this->getJson('/api/maps/retro?game_id=999999')
            ->assertStatus(200);

        $this->assertCount(0, $response->json('data'));
        $this->assertEquals(0, $response->json('meta')['total']);
    }
}
