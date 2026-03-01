<?php

namespace Tests\Feature\Maps;

use App\Constants\FormatConstants;
use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Traits\TestsDiscordAuthMiddleware;
use Tests\TestCase;
use PHPUnit\Metadata\Group;

class TransferCompletionsTest extends TestCase
{
    use TestsDiscordAuthMiddleware;

    protected function endpoint(): string
    {
        return '/api/maps/SOURCECODE/completions/transfer';
    }

    protected function method(): string
    {
        return 'PUT';
    }

    protected function requestData(): array
    {
        return ['target_map_code' => 'TARGETCODE'];
    }

    protected function expectedSuccessStatusCode(): int
    {
        return 204;
    }

    // Helper method to create maps with completions
    protected function createMapWithCompletions(
        string $mapCode,
        int $formatId,
        int $completionCount,
        ?User $player = null
    ): Map {
        // Find or create the map
        $map = Map::find($mapCode);
        if (!$map) {
            $map = Map::factory()->create(['code' => $mapCode]);
            MapListMeta::factory()->for($map)->create([
                'placement_curver' => 1,
                'placement_allver' => 1,
                'difficulty' => 1,
                'botb_difficulty' => 1,
            ]);
        }

        $player ??= User::factory()->create();

        for ($i = 0; $i < $completionCount; $i++) {
            $completion = Completion::factory()->create(['map_code' => $map->code]);
            CompletionMeta::factory()
                ->for($completion)
                ->accepted()
                ->withPlayers([$player])
                ->create(['format_id' => $formatId]);
        }

        return $map;
    }

    // ========== PERMISSION TESTS ==========

    #[Group('transfer')]
    #[Group('maps')]
    #[Group('completions')]
    public function test_transfer_without_edit_completion_permission_returns_403(): void
    {
        $sourceMap = $this->createMapWithCompletions('SOURCE', FormatConstants::MAPLIST, 3);
        $targetMap = Map::factory()->create(['code' => 'TARGET']);
        $user = $this->createUserWithPermissions([]);

        $this->actingAs($user, 'discord')
            ->putJson("/api/maps/{$sourceMap->code}/completions/transfer", [
                'target_map_code' => $targetMap->code,
            ])
            ->assertStatus(403)
            ->assertJson(['message' => 'Forbidden - You do not have permission to transfer completions.']);
    }

    #[Group('transfer')]
    #[Group('maps')]
    #[Group('completions')]
    public function test_transfer_format_admin_only_moves_completions_in_their_format(): void
    {
        // Setup: Source map has completions in Format 1 and Format 2
        $player = User::factory()->create();
        $sourceMap = $this->createMapWithCompletions('SOURCE', FormatConstants::MAPLIST, 3, $player);
        $this->createMapWithCompletions('SOURCE', FormatConstants::EXPERT_LIST, 2, $player);

        $targetMap = Map::factory()->create(['code' => 'TARGET']);

        // User only has edit:completion for Format 1 (MAPLIST)
        $user = $this->createUserWithPermissions([
            FormatConstants::MAPLIST => ['edit:completion'],
        ]);

        // BEFORE: Get Format 2 completions from source map
        $beforeExpertList = $this->actingAs($user, 'discord')
            ->getJson("/api/completions?map_code={$sourceMap->code}&format_id=" . FormatConstants::EXPERT_LIST)
            ->assertStatus(200)
            ->json('data');
        $this->assertCount(2, $beforeExpertList, 'Should have 2 EXPERT_LIST completions before transfer');

        // BEFORE: Get Format 1 completions from source map
        $beforeMaplist = $this->actingAs($user, 'discord')
            ->getJson("/api/completions?map_code={$sourceMap->code}&format_id=" . FormatConstants::MAPLIST)
            ->assertStatus(200)
            ->json('data');
        $this->assertCount(3, $beforeMaplist, 'Should have 3 MAPLIST completions before transfer');

        $this->actingAs($user, 'discord')
            ->putJson("/api/maps/{$sourceMap->code}/completions/transfer", [
                'target_map_code' => $targetMap->code,
            ])
            ->assertStatus(204);

        // AFTER: Get Format 2 completions from source map (should be unchanged)
        $afterExpertList = $this->actingAs($user, 'discord')
            ->getJson("/api/completions?map_code={$sourceMap->code}&format_id=" . FormatConstants::EXPERT_LIST)
            ->assertStatus(200)
            ->json('data');

        // AFTER: Get Format 1 completions from target map (should be transferred)
        $afterMaplist = $this->actingAs($user, 'discord')
            ->getJson("/api/completions?map_code={$targetMap->code}&format_id=" . FormatConstants::MAPLIST)
            ->assertStatus(200)
            ->json('data');

        // Verify EXPERT_LIST completions are exactly the same before and after
        $this->assertEquals($beforeExpertList, $afterExpertList, 'EXPERT_LIST completions should be unchanged');

        // Verify MAPLIST completions were transferred correctly (excluding fields that differ)
        $excludeKeys = ['*.id', '*.map_code', '*.created_on', '*.map'];
        $this->assertEquals(
            $this->except($beforeMaplist, $excludeKeys),
            $this->except($afterMaplist, $excludeKeys),
            'MAPLIST completions should match (excluding dynamic fields)'
        );
    }

    #[Group('transfer')]
    #[Group('maps')]
    #[Group('completions')]
    public function test_transfer_global_admin_moves_all_active_completions_on_map(): void
    {
        $player = User::factory()->create();
        $sourceMap = $this->createMapWithCompletions('SOURCE', FormatConstants::MAPLIST, 3, $player);
        $this->createMapWithCompletions('SOURCE', FormatConstants::EXPERT_LIST, 2, $player);

        $targetMap = Map::factory()->create(['code' => 'TARGET']);

        // Global admin with edit:completion on null (all formats)
        $user = $this->createUserWithPermissions([
            null => ['edit:completion'],
        ]);

        // Get completions from source map before transfer
        $beforeSource = $this->actingAs($user, 'discord')
            ->getJson("/api/completions?map_code={$sourceMap->code}")
            ->assertStatus(200)
            ->json('data');

        $this->actingAs($user, 'discord')
            ->putJson("/api/maps/{$sourceMap->code}/completions/transfer", [
                'target_map_code' => $targetMap->code,
            ])
            ->assertStatus(204);

        // Get completions from target map after transfer
        $afterTarget = $this->actingAs($user, 'discord')
            ->getJson("/api/completions?map_code={$targetMap->code}")
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(5, $afterTarget, 'Target map should have 5 completions after transfer');

        // Verify source map has no active completions
        $afterSource = $this->actingAs($user, 'discord')
            ->getJson("/api/completions?map_code={$sourceMap->code}")
            ->assertStatus(200)
            ->json('data');
        $this->assertCount(0, $afterSource, 'Source map should have 0 active completions after transfer');

        // Verify all completions were transferred correctly
        $excludeKeys = ['*.id', '*.map_code', '*.created_on', '*.map'];
        $this->assertEquals(
            $this->except($beforeSource, $excludeKeys),
            $this->except($afterTarget, $excludeKeys)
        );
    }

    // ========== VALIDATION TESTS ==========

    #[Group('transfer')]
    #[Group('maps')]
    #[Group('completions')]
    public function test_transfer_returns_404_if_source_map_does_not_exist(): void
    {
        $user = $this->createUserWithPermissions([null => ['edit:completion']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/maps/NONEXISTENT/completions/transfer', [
                'target_map_code' => 'TARGET',
            ])
            ->assertStatus(404)
            ->assertJson(['message' => 'Not Found']);
    }

    #[Group('transfer')]
    #[Group('maps')]
    #[Group('completions')]
    public function test_transfer_returns_422_if_target_map_does_not_exist(): void
    {
        $sourceMap = $this->createMapWithCompletions('SOURCE', FormatConstants::MAPLIST, 1);
        $user = $this->createUserWithPermissions([null => ['edit:completion']]);

        $this->actingAs($user, 'discord')
            ->putJson("/api/maps/{$sourceMap->code}/completions/transfer", [
                'target_map_code' => 'NONEXISTENT',
            ])
            ->assertStatus(422);
    }

    #[Group('transfer')]
    #[Group('maps')]
    #[Group('completions')]
    public function test_transfer_returns_422_if_target_is_same_as_source(): void
    {
        $sourceMap = $this->createMapWithCompletions('SOURCE', FormatConstants::MAPLIST, 1);
        $user = $this->createUserWithPermissions([null => ['edit:completion']]);

        $this->actingAs($user, 'discord')
            ->putJson("/api/maps/{$sourceMap->code}/completions/transfer", [
                'target_map_code' => $sourceMap->code,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['target_map_code']);
    }

    #[Group('transfer')]
    #[Group('maps')]
    #[Group('completions')]
    public function test_transfer_returns_422_if_target_map_code_missing(): void
    {
        $sourceMap = $this->createMapWithCompletions('SOURCE', FormatConstants::MAPLIST, 1);
        $user = $this->createUserWithPermissions([null => ['edit:completion']]);

        $this->actingAs($user, 'discord')
            ->putJson("/api/maps/{$sourceMap->code}/completions/transfer", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['target_map_code']);
    }

    // ========== FUNCTIONALITY TESTS ==========

    #[Group('transfer')]
    #[Group('maps')]
    #[Group('completions')]
    public function test_transfer_preserves_completion_data(): void
    {
        $player = User::factory()->create();
        $sourceMap = $this->createMapWithCompletions('SOURCE', FormatConstants::MAPLIST, 1, $player);
        $targetMap = Map::factory()->create(['code' => 'TARGET']);

        $user = $this->createUserWithPermissions([null => ['edit:completion']]);

        // Get original completion from source map
        $beforeSource = $this->actingAs($user, 'discord')
            ->getJson("/api/completions?map_code={$sourceMap->code}")
            ->assertStatus(200)
            ->json('data');
        $original = $beforeSource[0];

        $this->actingAs($user, 'discord')
            ->putJson("/api/maps/{$sourceMap->code}/completions/transfer", [
                'target_map_code' => $targetMap->code,
            ])
            ->assertStatus(204);

        // Get transferred completion from target map
        $afterTarget = $this->actingAs($user, 'discord')
            ->getJson("/api/completions?map_code={$targetMap->code}")
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(1, $afterTarget, 'Target map should have 1 completion after transfer');
        $transferred = $afterTarget[0];

        // Verify data was preserved, excluding fields that should differ
        $excludeKeys = [
            'id',
            'map_code',
            'map',
        ];

        $originalData = $this->except($original, $excludeKeys);
        $transferredData = $this->except($transferred, $excludeKeys);

        $this->assertEquals($originalData, $transferredData);
        $this->assertEquals($targetMap->code, $transferred['map_code']);
    }

    #[Group('transfer')]
    #[Group('maps')]
    #[Group('completions')]
    public function test_transfer_is_idempotent_when_source_has_no_completions(): void
    {
        $sourceMap = Map::factory()->create(['code' => 'SOURCE']);
        MapListMeta::factory()->for($sourceMap)->create([
            'placement_curver' => 1,
        ]);

        $targetMap = Map::factory()->create(['code' => 'TARGET']);

        $user = $this->createUserWithPermissions([null => ['edit:completion']]);

        // Get completions before transfer
        $before = $this->actingAs($user, 'discord')
            ->getJson('/api/completions')
            ->assertStatus(200)
            ->json('data');

        // Should return 204 even when no completions to transfer
        $this->actingAs($user, 'discord')
            ->putJson("/api/maps/{$sourceMap->code}/completions/transfer", [
                'target_map_code' => $targetMap->code,
            ])
            ->assertStatus(204);

        $after = $this->actingAs($user, 'discord')
            ->getJson('/api/completions')
            ->assertStatus(200)
            ->json('data');

        $this->assertEquals($before, $after);
    }
}
