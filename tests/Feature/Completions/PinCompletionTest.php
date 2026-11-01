<?php

namespace Tests\Feature\Completions;

use App\Constants\FormatConstants;
use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\User;
use PHPUnit\Metadata\Group;
use Tests\TestCase;
use Tests\Traits\TestsDiscordAuthMiddleware;

class PinCompletionTest extends TestCase
{
    use TestsDiscordAuthMiddleware;

    protected function endpoint(): string
    {
        return '/api/completions/1/pin';
    }

    protected function method(): string
    {
        return 'POST';
    }

    protected function expectedSuccessStatusCode(): int
    {
        return 204;
    }

    private function createCompletionAsPlayer(User $player, int $ageSeconds = 10): Completion
    {
        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create(['placement_curver' => 1]);

        $completion = Completion::factory()->create(['map_code' => $map->code]);

        $meta = CompletionMeta::factory()->create([
            'completion_id' => $completion->id,
            'format_id' => FormatConstants::MAPLIST,
            'accepted_by_id' => $player->discord_id,
            'deleted_on' => null,
            'created_on' => now()->subSeconds($ageSeconds),
        ]);
        $meta->players()->attach($player->discord_id);

        return $completion;
    }

    private function listCompletions(User $player, array $params = []): array
    {
        return $this->actingAs($player, 'discord')
            ->getJson('/api/completions?' . http_build_query(array_merge([
                'player_id' => $player->discord_id,
                'sort_by' => 'pinned',
                'sort_order' => 'desc',
            ], $params)))
            ->assertStatus(200)
            ->json('data');
    }

    // ========== AUTH ==========

    #[Group('post')]
    #[Group('completions')]
    #[Group('pin')]
    public function test_unpin_requires_auth(): void
    {
        $this->setupDiscordFakes();

        $this->postJson('/api/completions/1/unpin')
            ->assertStatus(401);
    }

    // ========== PIN ==========

    #[Group('post')]
    #[Group('completions')]
    #[Group('pin')]
    public function test_player_can_pin(): void
    {
        $player = User::factory()->create();
        $completion = $this->createCompletionAsPlayer($player);

        $this->actingAs($player, 'discord')
            ->postJson("/api/completions/{$completion->id}/pin")
            ->assertStatus(204);

        $actual = $this->listCompletions($player);

        $this->assertCount(1, $actual);
        $this->assertTrue($actual[0]['is_pinned']);
    }

    #[Group('post')]
    #[Group('completions')]
    #[Group('pin')]
    public function test_pin_is_idempotent(): void
    {
        $player = User::factory()->create();
        $completion = $this->createCompletionAsPlayer($player);

        $this->actingAs($player, 'discord')
            ->postJson("/api/completions/{$completion->id}/pin")
            ->assertStatus(204);

        $this->actingAs($player, 'discord')
            ->postJson("/api/completions/{$completion->id}/pin")
            ->assertStatus(204);

        $this->assertCount(1, $this->listCompletions($player, ['is_pinned' => 'only']));
    }

    #[Group('post')]
    #[Group('completions')]
    #[Group('pin')]
    public function test_non_player_returns_403(): void
    {
        $other = User::factory()->create();
        $completion = $this->createCompletionAsPlayer($other);

        // Even edit:completion permission doesn't allow pinning someone else's completion
        $user = $this->createUserWithPermissions([null => ['edit:completion']]);

        $this->actingAs($user, 'discord')
            ->postJson("/api/completions/{$completion->id}/pin")
            ->assertStatus(403);

        $this->assertCount(0, $this->listCompletions($user, ['is_pinned' => 'only']));
    }

    #[Group('post')]
    #[Group('completions')]
    #[Group('pin')]
    public function test_nonexistent_completion_returns_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'discord')
            ->postJson('/api/completions/999999/pin')
            ->assertStatus(404);
    }

    #[Group('post')]
    #[Group('completions')]
    #[Group('pin')]
    public function test_pin_cap_returns_422(): void
    {
        $player = User::factory()->create();
        $completions = [];
        for ($i = 0; $i < 5; $i++) {
            $completions[] = $this->createCompletionAsPlayer($player, 30 - $i);
        }

        foreach ($completions as $completion) {
            $this->actingAs($player, 'discord')
                ->postJson("/api/completions/{$completion->id}/pin")
                ->assertStatus(204);
        }

        // Pinning a 6th completion is rejected
        $sixth = $this->createCompletionAsPlayer($player, 1);
        $this->actingAs($player, 'discord')
            ->postJson("/api/completions/{$sixth->id}/pin")
            ->assertStatus(422);

        // Re-pinning an already pinned completion still works (idempotent, not counted)
        $this->actingAs($player, 'discord')
            ->postJson("/api/completions/{$completions[0]->id}/pin")
            ->assertStatus(204);

        // Unpinning frees a slot
        $this->actingAs($player, 'discord')
            ->postJson("/api/completions/{$completions[0]->id}/unpin")
            ->assertStatus(204);
        $this->actingAs($player, 'discord')
            ->postJson("/api/completions/{$sixth->id}/pin")
            ->assertStatus(204);
    }

    // ========== UNPIN ==========

    #[Group('post')]
    #[Group('completions')]
    #[Group('pin')]
    public function test_player_can_unpin(): void
    {
        $player = User::factory()->create();
        $completion = $this->createCompletionAsPlayer($player);

        $this->actingAs($player, 'discord')
            ->postJson("/api/completions/{$completion->id}/pin")
            ->assertStatus(204);

        $this->actingAs($player, 'discord')
            ->postJson("/api/completions/{$completion->id}/unpin")
            ->assertStatus(204);

        $actual = $this->listCompletions($player);

        $this->assertCount(1, $actual);
        $this->assertFalse($actual[0]['is_pinned']);
    }

    #[Group('post')]
    #[Group('completions')]
    #[Group('pin')]
    public function test_unpin_without_pin_is_idempotent(): void
    {
        $player = User::factory()->create();
        $completion = $this->createCompletionAsPlayer($player);

        $this->actingAs($player, 'discord')
            ->postJson("/api/completions/{$completion->id}/unpin")
            ->assertStatus(204);

        $this->assertCount(0, $this->listCompletions($player, ['is_pinned' => 'only']));
    }

    #[Group('post')]
    #[Group('completions')]
    #[Group('pin')]
    public function test_non_player_returns_403_on_unpin(): void
    {
        $other = User::factory()->create();
        $completion = $this->createCompletionAsPlayer($other);
        $user = User::factory()->create();

        $this->actingAs($user, 'discord')
            ->postJson("/api/completions/{$completion->id}/unpin")
            ->assertStatus(403);
    }

    // ========== GET /completions is_pinned filter & sort ==========

    #[Group('get')]
    #[Group('completions')]
    #[Group('pin')]
    public function test_is_pinned_only_returns_pinned_completions(): void
    {
        $player = User::factory()->create();
        $pinned = $this->createCompletionAsPlayer($player, 10);
        $unpinned = $this->createCompletionAsPlayer($player, 5);

        $this->actingAs($player, 'discord')
            ->postJson("/api/completions/{$pinned->id}/pin")
            ->assertStatus(204);

        $this->assertCount(1, $this->listCompletions($player, ['is_pinned' => 'only']));
        $this->assertCount(1, $this->listCompletions($player, ['is_pinned' => 'exclude']));
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('pin')]
    public function test_is_pinned_filter_is_per_user(): void
    {
        $player = User::factory()->create();
        $completion = $this->createCompletionAsPlayer($player);

        $this->actingAs($player, 'discord')
            ->postJson("/api/completions/{$completion->id}/pin")
            ->assertStatus(204);

        // Another player on their own completions sees nothing pinned
        $other = User::factory()->create();
        $this->assertCount(0, $this->listCompletions($other, ['is_pinned' => 'only']));
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('pin')]
    public function test_sort_by_pinned_puts_pinned_first(): void
    {
        $player = User::factory()->create();
        $older = $this->createCompletionAsPlayer($player, 20);
        $newer = $this->createCompletionAsPlayer($player, 10);
        $pinned = $this->createCompletionAsPlayer($player, 5);

        $this->actingAs($player, 'discord')
            ->postJson("/api/completions/{$pinned->id}/pin")
            ->assertStatus(204);

        $actual = $this->listCompletions($player);

        $this->assertEquals([$pinned->id, $newer->id, $older->id], array_column($actual, 'id'));
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('pin')]
    public function test_is_pinned_only_without_pins_returns_empty(): void
    {
        $player = User::factory()->create();
        $this->createCompletionAsPlayer($player);

        $this->assertCount(0, $this->listCompletions($player, ['is_pinned' => 'only']));
    }
}
