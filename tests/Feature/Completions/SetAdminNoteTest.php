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

class SetAdminNoteTest extends TestCase
{
    use TestsDiscordAuthMiddleware;

    protected function endpoint(): string
    {
        return '/api/completions/1/admin-note';
    }

    protected function method(): string
    {
        return 'PUT';
    }

    protected function requestData(): array
    {
        return ['admin_note' => 'Test note'];
    }

    protected function expectedSuccessStatusCode(): int
    {
        return 204;
    }

    private function createCompletion(int $formatId = FormatConstants::MAPLIST): Completion
    {
        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create(['placement_curver' => 1]);

        $completion = Completion::factory()->create(['map_code' => $map->code]);

        $player = User::factory()->create();
        $meta = CompletionMeta::factory()->create([
            'completion_id' => $completion->id,
            'format_id' => $formatId,
            'accepted_by_id' => null,
            'deleted_on' => null,
            'created_on' => now()->subSeconds(2),
        ]);
        $meta->players()->attach($player->discord_id);

        return $completion;
    }

    // ========== PERMISSION ==========

    #[Group('put')]
    #[Group('completions')]
    #[Group('admin_note')]
    public function test_without_permission_returns_403(): void
    {
        $completion = $this->createCompletion(FormatConstants::MAPLIST);
        $user = $this->createUserWithPermissions([]);

        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$completion->id}/admin-note", ['admin_note' => 'Note'])
            ->assertStatus(403);
    }

    #[Group('put')]
    #[Group('completions')]
    #[Group('admin_note')]
    public function test_wrong_format_permission_returns_403(): void
    {
        $completion = $this->createCompletion(FormatConstants::EXPERT_LIST);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);

        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$completion->id}/admin-note", ['admin_note' => 'Note'])
            ->assertStatus(403);
    }

    #[Group('put')]
    #[Group('completions')]
    #[Group('admin_note')]
    public function test_correct_format_permission_sets_note(): void
    {
        $completion = $this->createCompletion(FormatConstants::MAPLIST);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);

        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$completion->id}/admin-note", ['admin_note' => 'Flagged for review'])
            ->assertStatus(204);

        $this->actingAs($user, 'discord')
            ->getJson("/api/completions/{$completion->id}?include=admin_note")
            ->assertStatus(200)
            ->assertJsonPath('admin_note', 'Flagged for review');
    }

    #[Group('put')]
    #[Group('completions')]
    #[Group('admin_note')]
    public function test_global_permission_sets_note(): void
    {
        $completion = $this->createCompletion(FormatConstants::EXPERT_LIST);
        $user = $this->createUserWithPermissions([null => ['edit:completion']]);

        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$completion->id}/admin-note", ['admin_note' => 'Global note'])
            ->assertStatus(204);

        $this->actingAs($user, 'discord')
            ->getJson("/api/completions/{$completion->id}?include=admin_note")
            ->assertStatus(200)
            ->assertJsonPath('admin_note', 'Global note');
    }

    // ========== NOT FOUND ==========

    #[Group('put')]
    #[Group('completions')]
    #[Group('admin_note')]
    public function test_nonexistent_completion_returns_404(): void
    {
        $user = $this->createUserWithPermissions([null => ['edit:completion']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/completions/999999/admin-note', ['admin_note' => 'Note'])
            ->assertStatus(404);
    }

    // ========== VALIDATION ==========

    #[Group('put')]
    #[Group('completions')]
    #[Group('admin_note')]
    public function test_missing_field_returns_422(): void
    {
        $completion = $this->createCompletion();
        $user = $this->createUserWithPermissions([null => ['edit:completion']]);

        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$completion->id}/admin-note", [])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('admin_note');
    }

    #[Group('put')]
    #[Group('completions')]
    #[Group('admin_note')]
    public function test_empty_string_returns_422(): void
    {
        $completion = $this->createCompletion();
        $user = $this->createUserWithPermissions([null => ['edit:completion']]);

        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$completion->id}/admin-note", ['admin_note' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('admin_note');
    }
}
