<?php

namespace Tests\Feature\Completions;

use App\Constants\FormatConstants;
use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Bus;
use Tests\Traits\TestsDiscordAuthMiddleware;
use Tests\TestCase;
use PHPUnit\Metadata\Group;

class DeleteCompletionTest extends TestCase
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

    // Helper to create a completion for testing
    protected function createCompletionForDelete(int $formatId): Completion
    {
        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create([
            'placement_curver' => 1,
            'placement_allver' => 1,
            'difficulty' => 1,
            'botb_difficulty' => 1,
        ]);

        $completion = Completion::factory()->create(['map_code' => $map->code]);
        $player = User::factory()->create();

        CompletionMeta::factory()
            ->for($completion)
            ->accepted()
            ->withPlayers([$player])
            ->create(['format_id' => $formatId]);

        return $completion;
    }

    // ========== PERMISSION TESTS ==========

    #[Group('delete')]
    #[Group('completions')]
    public function test_delete_without_edit_completion_permission_returns_403(): void
    {
        $completion = $this->createCompletionForDelete(FormatConstants::MAPLIST);
        $user = $this->createUserWithPermissions([]);

        $this->actingAs($user, 'discord')
            ->deleteJson("/api/completions/{$completion->id}")
            ->assertStatus(403)
            ->assertJson(['message' => 'Forbidden - You do not have permission to delete completions for this format.']);
    }

    #[Group('delete')]
    #[Group('completions')]
    public function test_delete_with_wrong_format_permission_returns_403(): void
    {
        // User has Format A permission, completion is Format B
        $completion = $this->createCompletionForDelete(FormatConstants::EXPERT_LIST);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);

        $this->actingAs($user, 'discord')
            ->deleteJson("/api/completions/{$completion->id}")
            ->assertStatus(403)
            ->assertJson(['message' => 'Forbidden - You do not have permission to delete completions for this format.']);
    }

    #[Group('delete')]
    #[Group('completions')]
    public function test_delete_with_correct_format_permission_returns_204(): void
    {
        $completion = $this->createCompletionForDelete(FormatConstants::MAPLIST);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);

        $this->actingAs($user, 'discord')
            ->deleteJson("/api/completions/{$completion->id}")
            ->assertStatus(204);
    }

    #[Group('delete')]
    #[Group('completions')]
    public function test_delete_with_global_permission_returns_204(): void
    {
        $completion = $this->createCompletionForDelete(FormatConstants::EXPERT_LIST);
        $user = $this->createUserWithPermissions([null => ['edit:completion']]);

        $this->actingAs($user, 'discord')
            ->deleteJson("/api/completions/{$completion->id}")
            ->assertStatus(204);
    }

    // ========== NOT FOUND TESTS ==========

    #[Group('delete')]
    #[Group('completions')]
    public function test_delete_returns_404_for_non_existent_completion(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);

        $this->actingAs($user, 'discord')
            ->deleteJson('/api/completions/999999')
            ->assertStatus(404)
            ->assertJson(['message' => 'Not Found']);
    }

    // ========== FUNCTIONALITY TESTS ==========

    #[Group('delete')]
    #[Group('completions')]
    public function test_delete_sets_deleted_on_timestamp_and_is_verifiable_via_get(): void
    {
        $completion = $this->createCompletionForDelete(FormatConstants::MAPLIST);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);

        $deleteTime = now();
        $this->actingAs($user, 'discord')
            ->deleteJson("/api/completions/{$completion->id}")
            ->assertStatus(204);

        // Verify deleted_on is set via GET
        $actual = $this->actingAs($user, 'discord')
            ->getJson("/api/completions/{$completion->id}")
            ->assertStatus(200)
            ->json();

        $this->assertNotNull($actual['deleted_on']);
        $this->assertIsString($actual['deleted_on']);

        // Parse the datetime string and verify it's recent (within last second)
        $deletedOn = Carbon::parse($actual['deleted_on']);
        $this->assertLessThanOrEqual(now(), $deletedOn);
        $this->assertGreaterThan($deleteTime->subSecond(), $deletedOn);
    }

    #[Group('delete')]
    #[Group('completions')]
    public function test_delete_is_idempotent_and_does_not_update_existing_timestamp(): void
    {
        $completion = $this->createCompletionForDelete(FormatConstants::MAPLIST);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);

        // First delete
        $this->actingAs($user, 'discord')
            ->deleteJson("/api/completions/{$completion->id}")
            ->assertStatus(204);

        // Get the deleted_on timestamp after first delete
        $firstDelete = $this->actingAs($user, 'discord')
            ->getJson("/api/completions/{$completion->id}")
            ->assertStatus(200)
            ->json('deleted_on');

        // Parse the datetime to compare later
        $firstDeleteTime = Carbon::parse($firstDelete);

        // Wait a bit to ensure timestamp would be different if updated
        sleep(1);

        // Second delete should be idempotent
        $this->actingAs($user, 'discord')
            ->deleteJson("/api/completions/{$completion->id}")
            ->assertStatus(204);

        // Verify timestamp hasn't changed
        $secondDelete = $this->actingAs($user, 'discord')
            ->getJson("/api/completions/{$completion->id}")
            ->assertStatus(200)
            ->json('deleted_on');

        $secondDeleteTime = Carbon::parse($secondDelete);
        $this->assertEquals($firstDeleteTime, $secondDeleteTime, 'deleted_on timestamp should not change on second delete');
    }

    #[Group('delete')]
    #[Group('completions')]
    public function test_deleted_completion_excluded_by_default_in_list(): void
    {
        $completion = $this->createCompletionForDelete(FormatConstants::MAPLIST);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);

        // Verify completion appears in list before deletion
        $beforeDelete = $this->actingAs($user, 'discord')
            ->getJson('/api/completions?format_id=' . FormatConstants::MAPLIST)
            ->assertStatus(200)
            ->json('data');

        $completionIdsBefore = collect($beforeDelete)->pluck('id');
        $this->assertTrue($completionIdsBefore->contains($completion->id));

        // Delete the completion
        $this->actingAs($user, 'discord')
            ->deleteJson("/api/completions/{$completion->id}")
            ->assertStatus(204);

        // Verify completion is excluded from list by default (deleted=exclude is default)
        $afterDelete = $this->actingAs($user, 'discord')
            ->getJson('/api/completions?format_id=' . FormatConstants::MAPLIST)
            ->assertStatus(200)
            ->json('data');

        $completionIdsAfter = collect($afterDelete)->pluck('id');
        $this->assertFalse($completionIdsAfter->contains($completion->id));
    }
}
