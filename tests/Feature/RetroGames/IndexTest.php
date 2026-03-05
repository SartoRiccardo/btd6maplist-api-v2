<?php

namespace Tests\Feature\RetroGames;

use App\Models\RetroGame;
use Tests\TestCase;

class IndexTest extends TestCase
{
    #[Group('get')]
    #[Group('retro-games')]
    public function test_index_returns_standard_paginated_structure(): void
    {
        RetroGame::factory()->count(3)->create();

        $response = $this->getJson('/api/retro-games')
            ->assertStatus(200)
            ->json();

        $this->assertIsArray($response['data']);
        $this->assertArrayHasKey('current_page', $response['meta']);
        $this->assertArrayHasKey('last_page', $response['meta']);
        $this->assertArrayHasKey('per_page', $response['meta']);
        $this->assertArrayHasKey('total', $response['meta']);
    }

    #[Group('get')]
    #[Group('retro-games')]
    public function test_index_contains_all_seeded_records(): void
    {
        $count = 5;
        RetroGame::factory()->count($count)->create();

        $response = $this->getJson('/api/retro-games')->assertStatus(200)->json();

        $this->assertEquals($count, $response['meta']['total']);
        $this->assertCount($count, $response['data']);
    }

    #[Group('get')]
    #[Group('retro-games')]
    public function test_index_has_correct_record_structure(): void
    {
        $game = RetroGame::factory()->create([
            'game_id' => 6048,
            'game_name' => 'Test Game',
            'category_name' => 'Test Category',
            'subcategory_name' => 'Test Subcategory',
        ]);

        $actual = $this->getJson('/api/retro-games')->assertStatus(200)->json()['data'][0];

        $expected = RetroGame::jsonStructure([...$game->toArray()]);

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('retro-games')]
    public function test_index_returns_empty_data_if_no_records(): void
    {
        $response = $this->getJson('/api/retro-games')->assertStatus(200)->json();

        $this->assertEmpty($response['data']);
        $this->assertEquals(0, $response['meta']['total']);
    }

    #[Group('get')]
    #[Group('retro-games')]
    public function test_returns_games_with_default_pagination(): void
    {
        $count = 15;
        RetroGame::factory()->count($count)->create();

        $actual = $this->getJson('/api/retro-games')->assertStatus(200)->json();

        $this->assertEquals(1, $actual['meta']['current_page']);
        $this->assertEquals(15, $actual['meta']['per_page']);
        $this->assertEquals($count, $actual['meta']['total']);
        $this->assertCount($count, $actual['data']);
    }

    #[Group('get')]
    #[Group('retro-games')]
    public function test_returns_games_with_custom_pagination(): void
    {
        $total = 25;
        $page = 2;
        $perPage = 10;

        RetroGame::factory()->count($total)->create();

        $actual = $this->getJson("/api/retro-games?page={$page}&per_page={$perPage}")->assertStatus(200)->json();

        $this->assertEquals($page, $actual['meta']['current_page']);
        $this->assertEquals($perPage, $actual['meta']['per_page']);
        $this->assertEquals($total, $actual['meta']['total']);
        $this->assertEquals(3, $actual['meta']['last_page']);
        $this->assertCount($perPage, $actual['data']);
    }

    #[Group('get')]
    #[Group('retro-games')]
    public function test_returns_empty_array_on_page_overflow(): void
    {
        RetroGame::factory()->count(5)->create();

        $actual = $this->getJson('/api/retro-games?page=999')->assertStatus(200)->json();

        $this->assertEmpty($actual['data']);
        $this->assertEquals(999, $actual['meta']['current_page']);
        $this->assertEquals(1, $actual['meta']['last_page']);
    }

    #[Group('get')]
    #[Group('retro-games')]
    public function test_caps_per_page_at_maximum(): void
    {
        $this->getJson('/api/retro-games?per_page=101')
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('per_page');
    }

    #[Group('get')]
    #[Group('retro-games')]
    public function test_handles_per_page_parameter(): void
    {
        RetroGame::factory()->count(10)->create();

        $actual = $this->getJson('/api/retro-games?per_page=2')->assertStatus(200)->json();

        $this->assertEquals(2, $actual['meta']['per_page']);
        $this->assertCount(2, $actual['data']);
    }
}
