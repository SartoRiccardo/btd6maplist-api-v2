<?php

namespace Tests\Feature\RetroMap;

use App\Models\RetroGame;
use App\Models\RetroMap;
use Tests\TestCase;
use Tests\Traits\TestsDiscordAuthMiddleware;

class UpdateRetroMapTest extends TestCase
{
    use TestsDiscordAuthMiddleware;

    protected function endpoint(): string
    {
        return '/api/maps/retro/1';
    }

    protected function method(): string
    {
        return 'PUT';
    }

    protected function requestData(): array
    {
        return [
            'name' => 'Updated Retro Map',
        ];
    }

    protected function expectedSuccessStatusCode(): int
    {
        return 204;
    }

    #[Group('update')]
    #[Group('retro_maps')]
    public function test_update_retro_map_with_no_permission_returns_403(): void
    {
        $user = $this->createUserWithPermissions([]);
        $retroMap = RetroMap::factory()->create();

        $payload = [
            'name' => 'Updated Name',
            'sort_order' => 1,
            'preview_url' => 'https://example.com/preview.png',
            'retro_game_id' => $retroMap->retro_game_id,
        ];

        $this->actingAs($user, 'discord')
            ->putJson("/api/maps/retro/{$retroMap->id}", $payload)
            ->assertStatus(403)
            ->assertJson(['message' => 'Forbidden - You do not have permission to edit retro maps']);
    }

    #[Group('update')]
    #[Group('retro_maps')]
    public function test_update_retro_map_with_permission_returns_204(): void
    {
        $user = $this->createUserWithPermissions([null => ['edit:retro_map']]);
        $retroMap = RetroMap::factory()->create();

        $payload = [
            'name' => 'Updated Name',
            'sort_order' => $retroMap->sort_order,
            'preview_url' => $retroMap->preview_url,
            'retro_game_id' => $retroMap->retro_game_id,
        ];

        $this->actingAs($user, 'discord')
            ->putJson("/api/maps/retro/{$retroMap->id}", $payload)
            ->assertStatus(204);
    }

    #[Group('update')]
    #[Group('retro_maps')]
    public function test_update_retro_map_returns_404_if_not_found(): void
    {
        $user = $this->createUserWithPermissions([null => ['edit:retro_map']]);

        $payload = [
            'name' => 'Updated Name',
            'sort_order' => 1,
            'preview_url' => 'https://example.com/preview.png',
            'retro_game_id' => RetroGame::factory()->create()->id,
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/maps/retro/999999', $payload)
            ->assertStatus(404)
            ->assertJson(['message' => 'Not Found']);
    }

    #[Group('update')]
    #[Group('retro_maps')]
    public function test_update_retro_map_reorders_within_same_scope(): void
    {
        $user = $this->createUserWithPermissions([null => ['edit:retro_map']]);
        $retroGame = RetroGame::factory()->create();

        // Create maps at positions 1, 2, 3, 4, 5
        $maps = RetroMap::factory()
            ->for($retroGame, 'game')
            ->count(5)
            ->sequence(fn($sequence) => ['sort_order' => $sequence->index + 1, 'name' => "Map {$sequence->index}"])
            ->create();

        $mapToMove = $maps[2]; // Map 2 (index 2) at position 3

        // Move map at position 3 to position 1
        $this->actingAs($user, 'discord')
            ->putJson("/api/maps/retro/{$mapToMove->id}", [
                'name' => $mapToMove->name,
                'sort_order' => 1,
                'preview_url' => $mapToMove->preview_url,
                'retro_game_id' => $retroGame->id,
            ])
            ->assertStatus(204);

        // Verify reordering via GET index
        $actual = $this->getJson("/api/maps/retro?game_id={$retroGame->game_id}")
            ->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->json('data');

        // Verify sequential sort orders
        $this->assertEquals([1, 2, 3, 4, 5], array_column($actual, 'sort_order'));

        // Verify correct map order: Map 2 (moved), Map 0, Map 1, Map 3, Map 4
        $this->assertEquals([$maps[2]->id, $maps[0]->id, $maps[1]->id, $maps[3]->id, $maps[4]->id], array_column($actual, 'id'));
    }

    #[Group('update')]
    #[Group('retro_maps')]
    public function test_update_retro_map_reorders_across_different_scopes(): void
    {
        $user = $this->createUserWithPermissions([null => ['edit:retro_map']]);
        $game1 = RetroGame::factory()->create();
        $game2 = RetroGame::factory()->create();

        // Game 1: maps at positions 1, 2, 3
        $game1Maps = RetroMap::factory()
            ->for($game1, 'game')
            ->count(3)
            ->sequence(fn($sequence) => ['sort_order' => $sequence->index + 1])
            ->create();

        // Game 2: maps at positions 1, 2
        $game2Maps = RetroMap::factory()
            ->for($game2, 'game')
            ->count(2)
            ->sequence(fn($sequence) => ['sort_order' => $sequence->index + 1])
            ->create();

        $mapToMove = $game1Maps[1]; // Position 2 in game 1

        // Move game1Map2 to game2 at position 2
        $this->actingAs($user, 'discord')
            ->putJson("/api/maps/retro/{$mapToMove->id}", [
                'name' => $mapToMove->name,
                'sort_order' => 2,
                'preview_url' => $mapToMove->preview_url,
                'retro_game_id' => $game2->id,
            ])
            ->assertStatus(204);

        // Verify old scope (game1): 2 maps remain, sequential
        $game1Actual = $this->getJson("/api/maps/retro?game_id={$game1->game_id}")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->json('data');

        $this->assertEquals([1, 2], array_column($game1Actual, 'sort_order'));
        $this->assertEquals([$game1Maps[0]->id, $game1Maps[2]->id], array_column($game1Actual, 'id'));

        // Verify new scope (game2): 3 maps, sequential
        $game2Actual = $this->getJson("/api/maps/retro?game_id={$game2->game_id}")
            ->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->json('data');

        $this->assertEquals([1, 2, 3], array_column($game2Actual, 'sort_order'));
        $this->assertEquals([$game2Maps[0]->id, $mapToMove->id, $game2Maps[1]->id], array_column($game2Actual, 'id'));
    }

    #[Group('update')]
    #[Group('retro_maps')]
    public function test_update_retro_map_full_update(): void
    {
        $user = $this->createUserWithPermissions([null => ['edit:retro_map']]);
        $retroMap = RetroMap::factory()->create();

        $newGame = RetroGame::factory()->create();

        $payload = [
            'name' => 'Updated Name',
            'sort_order' => 1,
            'preview_url' => 'https://example.com/updated.png',
            'retro_game_id' => $newGame->id,
        ];

        $this->actingAs($user, 'discord')
            ->putJson("/api/maps/retro/{$retroMap->id}", $payload)
            ->assertStatus(204);

        // Verify via GET
        $actual = $this->getJson("/api/maps/retro/{$retroMap->id}")
            ->assertStatus(200)
            ->json();

        $this->assertEquals('Updated Name', $actual['name']);
        $this->assertEquals('https://example.com/updated.png', $actual['preview_url']);
        $this->assertEquals(1, $actual['sort_order']);
        $this->assertEquals($newGame->id, $actual['retro_game_id']);
    }
}
