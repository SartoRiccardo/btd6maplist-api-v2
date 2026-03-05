<?php

namespace Tests\Feature\RetroMap;

use App\Models\RetroGame;
use App\Models\RetroMap;
use Tests\TestCase;
use Tests\Traits\TestsDiscordAuthMiddleware;

class StoreRetroMapTest extends TestCase
{
    use TestsDiscordAuthMiddleware;

    protected function endpoint(): string
    {
        return '/api/maps/retro';
    }

    protected function method(): string
    {
        return 'POST';
    }

    protected function requestData(): array
    {
        return [
            'name' => 'Test Retro Map',
            'sort_order' => 1,
            'preview_url' => 'https://example.com/preview.png',
            'retro_game_id' => RetroGame::factory()->create()->id,
        ];
    }

    protected function expectedSuccessStatusCode(): int
    {
        return 201;
    }

    #[Group('store')]
    #[Group('retro_maps')]
    public function test_create_retro_map_with_no_permission_returns_403(): void
    {
        $user = $this->createUserWithPermissions([]);
        $retroGame = RetroGame::factory()->create();

        $payload = [
            'name' => 'Test Retro Map',
            'sort_order' => 1,
            'preview_url' => 'https://example.com/preview.png',
            'retro_game_id' => $retroGame->id,
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps/retro', $payload)
            ->assertStatus(403)
            ->assertJson(['message' => 'Forbidden - You do not have permission to create retro maps']);
    }

    #[Group('store')]
    #[Group('retro_maps')]
    public function test_create_retro_map_with_permission_returns_201(): void
    {
        $user = $this->createUserWithPermissions([null => ['create:retro_map']]);
        $retroGame = RetroGame::factory()->create();

        $payload = [
            'name' => 'Test Retro Map',
            'sort_order' => 1,
            'preview_url' => 'https://example.com/preview.png',
            'retro_game_id' => $retroGame->id,
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps/retro', $payload)
            ->assertStatus(201)
            ->assertJsonStructure(['id']);
    }

    #[Group('store')]
    #[Group('retro_maps')]
    public function test_create_retro_map_validates_input(): void
    {
        $user = $this->createUserWithPermissions([null => ['create:retro_map']]);

        $payload = [
            'name' => '',
            'sort_order' => 'invalid',
            'preview_url' => 'not-a-url',
            'retro_game_id' => 999999,
        ];

        $response = $this->actingAs($user, 'discord')
            ->postJson('/api/maps/retro', $payload)
            ->assertStatus(422);

        $errors = $response->json('errors');
        $this->assertEqualsCanonicalizing(['name', 'preview_url', 'retro_game_id', 'sort_order'], array_keys($errors));
    }

    #[Group('store')]
    #[Group('retro_maps')]
    public function test_create_retro_map_reorders_existing_maps_within_game_category_scope(): void
    {
        $user = $this->createUserWithPermissions([null => ['create:retro_map']]);
        $retroGame = RetroGame::factory()->create();

        // Create existing maps at positions 1, 2, 3
        RetroMap::factory()
            ->for($retroGame, 'game')
            ->count(3)
            ->sequence(fn($sequence) => ['sort_order' => $sequence->index + 1, 'name' => "Map {$sequence->index}"])
            ->create();

        // Insert new map at position 2
        $this->actingAs($user, 'discord')
            ->postJson('/api/maps/retro', [
                'name' => 'New Map',
                'sort_order' => 2,
                'preview_url' => 'https://example.com/preview.png',
                'retro_game_id' => $retroGame->id,
            ])
            ->assertStatus(201)
            ->assertJsonStructure(['id']);

        // Verify reordering via GET
        $actual = $this->getJson("/api/maps/retro?game_id={$retroGame->game_id}")
            ->assertStatus(200)
            ->assertJsonCount(4, 'data')
            ->json('data');

        // Verify sequential sort orders: 1, 2, 3, 4
        $this->assertEquals([1, 2, 3, 4], array_column($actual, 'sort_order'));

        // Verify correct order: Map 0, New Map, Map 1, Map 2
        $this->assertEquals(['Map 0', 'New Map', 'Map 1', 'Map 2'], array_column($actual, 'name'));
    }

    #[Group('store')]
    #[Group('retro_maps')]
    public function test_create_retro_map_validates_sort_order_max(): void
    {
        $user = $this->createUserWithPermissions([null => ['create:retro_map']]);
        $retroGame = RetroGame::factory()->create();

        // Create existing maps at positions 1, 2, 3
        RetroMap::factory()
            ->for($retroGame, 'game')
            ->count(3)
            ->sequence(fn($sequence) => ['sort_order' => $sequence->index + 1])
            ->create();

        $payload = [
            'name' => 'New Map',
            'sort_order' => 10, // Max allowed is 4 (max existing + 1)
            'preview_url' => 'https://example.com/preview.png',
            'retro_game_id' => $retroGame->id,
        ];

        $response = $this->actingAs($user, 'discord')
            ->postJson('/api/maps/retro', $payload)
            ->assertStatus(422);

        $errors = $response->json('errors');
        $this->assertEqualsCanonicalizing(['sort_order'], array_keys($errors));
    }
}
