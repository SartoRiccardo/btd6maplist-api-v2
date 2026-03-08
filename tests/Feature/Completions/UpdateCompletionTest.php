<?php

namespace Tests\Feature\Completions;

use App\Constants\FormatConstants;
use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\LeastCostChimps;
use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Bus;
use Tests\Traits\TestsDiscordAuthMiddleware;
use Tests\TestCase;
use PHPUnit\Metadata\Group;

class UpdateCompletionTest extends TestCase
{
    use TestsDiscordAuthMiddleware;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
    }

    protected function endpoint(): string
    {
        return '/api/completions/1';
    }

    protected function method(): string
    {
        return 'PUT';
    }

    protected function requestData(): array
    {
        return [];
    }

    protected function expectedSuccessStatusCode(): int
    {
        return 204;
    }

    // Helper to create a completion for testing
    protected function createCompletionForUpdate(
        int $formatId,
        bool $accepted = false,
        ?User $player = null,
        ?User $acceptor = null,
        ?Carbon $createdOn = null
    ): Completion {
        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create([
            'placement_curver' => 1,
            'placement_allver' => 1,
            'difficulty' => 1,
            'botb_difficulty' => 1,
        ]);

        $completion = Completion::factory()->create(['map_code' => $map->code]);

        if ($accepted && !$acceptor) {
            $acceptor = User::factory()->create();
        }

        $meta = CompletionMeta::factory()->for($completion)->create([
            'format_id' => $formatId,
            'black_border' => false,
            'no_geraldo' => false,
            'accepted_by_id' => $acceptor?->discord_id,
            'created_on' => $createdOn ?? Carbon::now(),
        ]);

        $player ??= User::factory()->create();
        $meta->players()->attach($player->discord_id);

        return $completion;
    }

    // Helper to get the active meta for a completion
    protected function getActiveMeta(Completion $completion): ?CompletionMeta
    {
        return CompletionMeta::where('completion_id', $completion->id)
            ->whereNull('deleted_on')
            ->orderBy('created_on', 'desc')
            ->first();
    }

    // ========== PERMISSION TESTS ==========

    /**
     * Update completion without edit:completion on current format returns 403
     */
    #[Group('put')]
    #[Group('completions')]
    public function test_update_without_current_format_permission_returns_403(): void
    {
        $completion = $this->createCompletionForUpdate(FormatConstants::MAPLIST);
        $user = $this->createUserWithPermissions([FormatConstants::EXPERT_LIST => ['edit:completion']]);

        $player = User::factory()->create();

        $payload = [
            'format_id' => FormatConstants::MAPLIST,
            'players' => [$player->discord_id],
            'accept' => true,
        ];

        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$completion->id}", $payload)
            ->assertStatus(403)
            ->assertJson(['message' => 'Forbidden - You do not have edit:completion permission for the current format and new format.']);
    }

    /**
     * Update completion without edit:completion on new format returns 403
     */
    #[Group('put')]
    #[Group('completions')]
    public function test_update_without_new_format_permission_returns_403(): void
    {
        $completion = $this->createCompletionForUpdate(FormatConstants::MAPLIST);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);

        $player = User::factory()->create();

        $payload = [
            'format_id' => FormatConstants::EXPERT_LIST,
            'players' => [$player->discord_id],
            'accept' => true,
        ];

        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$completion->id}", $payload)
            ->assertStatus(403)
            ->assertJson(['message' => 'Forbidden - You do not have edit:completion permission for the new format.']);
    }

    /**
     * Update completion without any permissions returns 403
     */
    #[Group('put')]
    #[Group('completions')]
    public function test_update_without_any_permission_returns_403(): void
    {
        $completion = $this->createCompletionForUpdate(FormatConstants::MAPLIST);
        $user = $this->createUserWithPermissions([]);

        $player = User::factory()->create();

        $payload = [
            'format_id' => FormatConstants::MAPLIST,
            'players' => [$player->discord_id],
            'accept' => true,
        ];

        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$completion->id}", $payload)
            ->assertStatus(403)
            ->assertJson(['message' => 'Forbidden - You do not have edit:completion permission for the current format and new format.']);
    }

    // ========== BUSINESS RULE TESTS ==========

    /**
     * User cannot add themselves to the players list returns 403
     */
    #[Group('put')]
    #[Group('completions')]
    public function test_update_add_self_to_players_returns_403(): void
    {
        $completion = $this->createCompletionForUpdate(FormatConstants::MAPLIST);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);

        $payload = [
            'format_id' => FormatConstants::MAPLIST,
            'players' => [$user->discord_id],
            'accept' => true,
        ];

        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$completion->id}", $payload)
            ->assertStatus(403)
            ->assertJson(['message' => 'You cannot add yourself to the players list.']);
    }

    /**
     * User cannot modify their own completion returns 403
     */
    #[Group('put')]
    #[Group('completions')]
    public function test_update_own_completion_returns_403(): void
    {
        // Create a player with permissions first
        $playerInCompletion = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);

        // Create completion with this player
        $completion = $this->createCompletionForUpdate(FormatConstants::MAPLIST, accepted: false, player: $playerInCompletion);

        $newPlayer = User::factory()->create();

        $payload = [
            'format_id' => FormatConstants::MAPLIST,
            'players' => [$newPlayer->discord_id],
            'accept' => true,
        ];

        $this->actingAs($playerInCompletion, 'discord')
            ->putJson("/api/completions/{$completion->id}", $payload)
            ->assertStatus(403)
            ->assertJson(['message' => 'You cannot modify your own completion.']);
    }

    /**
     * Empty players array returns 422
     */
    #[Group('put')]
    #[Group('completions')]
    public function test_update_empty_players_returns_422(): void
    {
        $completion = $this->createCompletionForUpdate(FormatConstants::MAPLIST);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);

        $payload = [
            'format_id' => FormatConstants::MAPLIST,
            'players' => [],
            'accept' => true,
        ];

        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$completion->id}", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['players']);
    }

    /**
     * Duplicate players returns 422 with exact key path
     */
    #[Group('put')]
    #[Group('completions')]
    public function test_update_duplicate_players_returns_422(): void
    {
        $completion = $this->createCompletionForUpdate(FormatConstants::MAPLIST);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);
        $player = User::factory()->create();

        $payload = [
            'format_id' => FormatConstants::MAPLIST,
            'players' => [$player->discord_id, $player->discord_id],
            'accept' => true,
        ];

        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$completion->id}", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['players.1']);
    }

    /**
     * Completion not found returns 404
     */
    #[Group('put')]
    #[Group('completions')]
    public function test_completion_not_found_returns_404(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);
        $player = User::factory()->create();

        $payload = [
            'format_id' => FormatConstants::MAPLIST,
            'players' => [$player->discord_id],
            'accept' => true,
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/completions/999999', $payload)
            ->assertStatus(404);
    }

    /**
     * Updating a deleted completion returns 422
     */
    #[Group('put')]
    #[Group('completions')]
    public function test_update_deleted_completion_returns_422(): void
    {
        // Create completion and mark it as deleted
        $completion = $this->createCompletionForUpdate(FormatConstants::MAPLIST);
        $meta = $this->getActiveMeta($completion);
        $meta->deleted_on = Carbon::now();
        $meta->save();

        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);
        $newPlayer = User::factory()->create();

        $payload = [
            'format_id' => FormatConstants::MAPLIST,
            'players' => [$newPlayer->discord_id],
            'accept' => true,
        ];

        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$completion->id}", $payload)
            ->assertStatus(422)
            ->assertJson(['message' => 'Cannot update a deleted completion.']);
    }

    /**
     * accept=false does not accept unaccepted completion
     */
    #[Group('put')]
    #[Group('completions')]
    public function test_accept_false_does_not_accept_unaccepted_completion(): void
    {
        $completion = $this->createCompletionForUpdate(FormatConstants::MAPLIST, accepted: false);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);
        $player = User::factory()->create();

        $payload = [
            'format_id' => FormatConstants::MAPLIST,
            'players' => [$player->discord_id],
            'accept' => false,
        ];

        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$completion->id}", $payload)
            ->assertStatus(204);

        // Verify still unaccepted
        $actual = $this->actingAs($user, 'discord')
            ->getJson("/api/completions/{$completion->id}")
            ->assertStatus(200)
            ->json();

        $this->assertNull($actual['accepted_by']);
    }

    /**
     * Updating previously accepted completion with accept=true keeps old acceptor
     */
    #[Group('put')]
    #[Group('completions')]
    public function test_previously_accepted_completion_keeps_old_acceptor(): void
    {
        $completion = $this->createCompletionForUpdate(FormatConstants::MAPLIST, accepted: true);
        $meta = $this->getActiveMeta($completion);
        $originalAcceptorId = $meta->accepted_by_id;
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);
        $newPlayer = User::factory()->create();

        $payload = [
            'format_id' => FormatConstants::MAPLIST,
            'players' => [$newPlayer->discord_id],
            'accept' => true, // This should be ignored
        ];

        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$completion->id}", $payload)
            ->assertStatus(204);

        // Verify original acceptor is preserved
        $actual = $this->actingAs($user, 'discord')
            ->getJson("/api/completions/{$completion->id}")
            ->assertStatus(200)
            ->json();

        $this->assertEquals($originalAcceptorId, $actual['accepted_by']['discord_id']);
    }

    /**
     * accept=false is completely ignored for previously accepted completion
     */
    #[Group('put')]
    #[Group('completions')]
    public function test_accept_false_ignored_for_previously_accepted_completion(): void
    {
        $completion = $this->createCompletionForUpdate(FormatConstants::MAPLIST, accepted: true);
        $meta = $this->getActiveMeta($completion);
        $originalAcceptorId = $meta->accepted_by_id;
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);
        $newPlayer = User::factory()->create();

        $payload = [
            'format_id' => FormatConstants::MAPLIST,
            'players' => [$newPlayer->discord_id],
            'accept' => false, // Should be ignored
        ];

        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$completion->id}", $payload)
            ->assertStatus(204);

        // Verify original acceptor is still there
        $actual = $this->actingAs($user, 'discord')
            ->getJson("/api/completions/{$completion->id}")
            ->assertStatus(200)
            ->json();

        $this->assertEquals($originalAcceptorId, $actual['accepted_by']['discord_id']);
    }

    /**
     * accept=false when user is the acceptor is completely ignored
     */
    #[Group('put')]
    #[Group('completions')]
    public function test_accept_false_when_user_is_acceptor_is_ignored(): void
    {
        // Create an acceptor with permissions first
        $acceptor = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);

        // Create completion with this acceptor
        $completion = $this->createCompletionForUpdate(FormatConstants::MAPLIST, accepted: true, acceptor: $acceptor);

        $newPlayer = User::factory()->create();

        $payload = [
            'format_id' => FormatConstants::MAPLIST,
            'players' => [$newPlayer->discord_id],
            'accept' => false, // User tries to revoke their own acceptance
        ];

        $this->actingAs($acceptor, 'discord')
            ->putJson("/api/completions/{$completion->id}", $payload)
            ->assertStatus(204);

        // Verify acceptor is still there (not revoked)
        $actual = $this->actingAs($acceptor, 'discord')
            ->getJson("/api/completions/{$completion->id}")
            ->assertStatus(200)
            ->json();

        $this->assertEquals($acceptor->discord_id, $actual['accepted_by']['discord_id']);
    }

    /**
     * Empty payload returns 422 with exact error keys
     */
    #[Group('put')]
    #[Group('completions')]
    public function test_empty_payload_returns_exact_error_keys(): void
    {
        $completion = $this->createCompletionForUpdate(FormatConstants::MAPLIST);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);

        $actual = $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$completion->id}", [])
            ->assertStatus(422)
            ->json();

        $expected = ['format_id', 'players', 'accept'];
        $actualKeys = array_keys($actual['errors']);
        sort($expected);
        sort($actualKeys);
        $this->assertEquals($expected, $actualKeys);
    }

    // ========== HAPPY PATH TESTS ==========

    /**
     * Update completion happy path - new players, new meta
     */
    #[Group('put')]
    #[Group('completions')]
    public function test_update_completion_happy_path(): void
    {
        $completion = $this->createCompletionForUpdate(FormatConstants::MAPLIST);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);
        $newPlayer = User::factory()->create();

        $payload = [
            'format_id' => FormatConstants::MAPLIST,
            'black_border' => true,
            'no_geraldo' => true,
            'players' => [$newPlayer->discord_id],
            'lcc' => ['leftover' => 6000],
            'accept' => true,
        ];

        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$completion->id}", $payload)
            ->assertStatus(204);

        // Verify with GET
        $actual = $this->actingAs($user, 'discord')
            ->getJson("/api/completions/{$completion->id}")
            ->assertStatus(200)
            ->json();

        $actual = $this->pick($actual, [
            'map_code',
            'format_id',
            'black_border',
            'no_geraldo',
            'lcc.leftover',
            'deleted_on',
            'accepted_by.discord_id',
            'players.*.discord_id',
            'subm_proof_vid',
            'is_current_lcc',
            'map.code',
        ]);

        $expected = [
            'map_code' => $completion->map_code,
            'format_id' => FormatConstants::MAPLIST,
            'black_border' => true,
            'no_geraldo' => true,
            'lcc' => [
                'leftover' => 6000,
            ],
            'deleted_on' => null,
            'accepted_by' => [
                'discord_id' => $user->discord_id,
            ],
            'players' => [
                ['discord_id' => $newPlayer->discord_id],
            ],
            'subm_proof_vid' => [],
            'is_current_lcc' => true,
            'map' => [
                'code' => $completion->map_code,
            ],
        ];

        $this->assertEquals($expected, $actual);
    }

    /**
     * Update completion LCC to null removes LCC
     */
    #[Group('put')]
    #[Group('completions')]
    public function test_update_completion_lcc_to_null_removes_lcc(): void
    {
        $completion = $this->createCompletionForUpdate(FormatConstants::MAPLIST);

        // Add LCC
        $lcc = LeastCostChimps::factory()->create(['leftover' => 5000]);
        $meta = $this->getActiveMeta($completion);
        $meta->lcc_id = $lcc->id;
        $meta->save();

        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);
        $newPlayer = User::factory()->create();

        $payload = [
            'format_id' => FormatConstants::MAPLIST,
            'players' => [$newPlayer->discord_id],
            'lcc' => null, // Explicitly remove LCC
            'accept' => true,
        ];

        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$completion->id}", $payload)
            ->assertStatus(204);

        // Verify LCC was removed
        $actual = $this->actingAs($user, 'discord')
            ->getJson("/api/completions/{$completion->id}")
            ->assertStatus(200)
            ->json();

        $this->assertNull($actual['lcc']);
    }

    /**
     * Update completion keeps old version (verify with GET & timestamp)
     */
    #[Group('put')]
    #[Group('completions')]
    public function test_update_keeps_old_version_verified_with_timestamp(): void
    {
        // Create completion with an explicit old timestamp (1 hour ago)
        $oldTimestamp = Carbon::now()->subHour();
        $completion = $this->createCompletionForUpdate(
            FormatConstants::MAPLIST,
            accepted: false,
            createdOn: $oldTimestamp
        );

        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);
        $newPlayer = User::factory()->create();

        // Get the old player BEFORE update
        $oldMeta = $this->getActiveMeta($completion);
        $oldMeta->load('players');
        $oldPlayer = $oldMeta->players->first();

        $payload = [
            'format_id' => FormatConstants::MAPLIST,
            'players' => [$newPlayer->discord_id],
            'accept' => true,
        ];

        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$completion->id}", $payload)
            ->assertStatus(204);

        // Query with timestamp from when the old version was created
        $queryTimestamp = $oldTimestamp->addMinute()->unix();

        // Verify old version is still accessible with timestamp
        $oldVersion = $this->actingAs($user, 'discord')
            ->getJson("/api/completions/{$completion->id}?timestamp={$queryTimestamp}")
            ->assertStatus(200)
            ->json();

        // Should have old player
        $this->assertEquals($oldPlayer->discord_id, $oldVersion['players'][0]['discord_id']);
    }
}
