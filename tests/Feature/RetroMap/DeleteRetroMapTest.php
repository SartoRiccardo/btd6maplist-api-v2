<?php

namespace Tests\Feature\RetroMap;

use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\RetroGame;
use App\Models\RetroMap;
use Carbon\Carbon;
use Tests\TestCase;
use Tests\Traits\TestsDiscordAuthMiddleware;

class DeleteRetroMapTest extends TestCase
{
    use TestsDiscordAuthMiddleware;

    protected function endpoint(): string
    {
        return '/api/maps/retro/1';
    }

    protected function method(): string
    {
        return 'DELETE';
    }

    protected function requestData(): array
    {
        return [];
    }

    protected function expectedSuccessStatusCode(): int
    {
        return 204;
    }

    #[Group('delete')]
    #[Group('retro_maps')]
    public function test_delete_retro_map_with_no_permission_returns_403(): void
    {
        $user = $this->createUserWithPermissions([]);
        $retroMap = RetroMap::factory()->create();

        $this->actingAs($user, 'discord')
            ->deleteJson("/api/maps/retro/{$retroMap->id}")
            ->assertStatus(403)
            ->assertJson(['message' => 'Forbidden - You do not have permission to delete retro maps']);
    }

    #[Group('delete')]
    #[Group('retro_maps')]
    public function test_delete_retro_map_with_permission_returns_204(): void
    {
        $user = $this->createUserWithPermissions([null => ['delete:retro_map']]);
        $retroMap = RetroMap::factory()->create();

        $this->actingAs($user, 'discord')
            ->deleteJson("/api/maps/retro/{$retroMap->id}")
            ->assertStatus(204);
    }

    #[Group('delete')]
    #[Group('retro_maps')]
    public function test_delete_retro_map_returns_404_if_not_found(): void
    {
        $user = $this->createUserWithPermissions([null => ['delete:retro_map']]);

        $this->actingAs($user, 'discord')
            ->deleteJson('/api/maps/retro/999999')
            ->assertStatus(404)
            ->assertJson(['message' => 'Not Found']);
    }

    #[Group('delete')]
    #[Group('retro_maps')]
    public function test_delete_retro_map_fails_if_linked_to_currently_active_remake(): void
    {
        $user = $this->createUserWithPermissions([null => ['delete:retro_map']]);

        $retroMap = RetroMap::factory()->create();
        $map = Map::factory()->create();

        // Create active meta referencing the retro map
        MapListMeta::factory()->for($map)->create([
            'remake_of' => $retroMap->id,
            'created_on' => Carbon::now()->subDay(),
        ]);

        $this->actingAs($user, 'discord')
            ->deleteJson("/api/maps/retro/{$retroMap->id}")
            ->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot delete retro map referenced by active maps',
                'map_codes' => [$map->code],
            ]);
    }

    #[Group('delete')]
    #[Group('retro_maps')]
    public function test_delete_retro_map_succeeds_if_linked_only_to_outdated_remake(): void
    {
        $user = $this->createUserWithPermissions([null => ['delete:retro_map']]);

        $retroMap = RetroMap::factory()->create();
        $map = Map::factory()->create();

        // Create outdated meta (with deleted_on)
        MapListMeta::factory()->for($map)->create([
            'remake_of' => $retroMap->id,
            'created_on' => Carbon::now()->subYear(),
            'deleted_on' => Carbon::now()->subMonth(),
        ]);

        // Create newer meta that doesn't reference the retro map
        MapListMeta::factory()->for($map)->create([
            'remake_of' => null,
            'created_on' => Carbon::now()->subWeek(),
        ]);

        $this->actingAs($user, 'discord')
            ->deleteJson("/api/maps/retro/{$retroMap->id}")
            ->assertStatus(204);
    }

    #[Group('delete')]
    #[Group('retro_maps')]
    public function test_delete_retro_map_soft_deletes_successfully(): void
    {
        $user = $this->createUserWithPermissions([null => ['delete:retro_map']]);
        $retroMap = RetroMap::factory()->create();

        $this->actingAs($user, 'discord')
            ->deleteJson("/api/maps/retro/{$retroMap->id}")
            ->assertStatus(204);

        // Verify soft deleted
        $this->assertDatabaseHas('retro_maps', [
            'id' => $retroMap->id,
        ]);

        $deletedMap = RetroMap::withTrashed()->find($retroMap->id);
        $this->assertNotNull($deletedMap->deleted_at);

        // Verify not returned in index
        $this->getJson('/api/maps/retro')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');

        // Verify not returned in show
        $this->getJson("/api/maps/retro/{$retroMap->id}")
            ->assertStatus(404);
    }

    #[Group('delete')]
    #[Group('retro_maps')]
    public function test_delete_retro_map_reorders_remaining_maps(): void
    {
        $user = $this->createUserWithPermissions([null => ['delete:retro_map']]);
        $retroGame = RetroGame::factory()->create();

        // Create maps at positions 1, 2, 3, 4, 5
        $maps = RetroMap::factory()
            ->for($retroGame, 'game')
            ->count(5)
            ->sequence(fn($sequence) => ['sort_order' => $sequence->index + 1, 'name' => "Map {$sequence->index}"])
            ->create();

        $mapToDelete = $maps[2]; // Map 3

        // Delete map at position 3
        $this->actingAs($user, 'discord')
            ->deleteJson("/api/maps/retro/{$mapToDelete->id}")
            ->assertStatus(204);

        // Verify reordering via GET index
        $actual = $this->getJson("/api/maps/retro?game_id={$retroGame->game_id}")
            ->assertStatus(200)
            ->assertJsonCount(4, 'data')
            ->json('data');

        // Verify sequential sort orders: 1, 2, 3, 4
        $this->assertEquals([1, 2, 3, 4], array_column($actual, 'sort_order'));

        // Verify correct maps remain in correct order
        $this->assertEquals(['Map 0', 'Map 1', 'Map 3', 'Map 4'], array_column($actual, 'name'));
    }
}
