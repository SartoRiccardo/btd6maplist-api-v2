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

class DeleteAdminNoteTest extends TestCase
{
    use TestsDiscordAuthMiddleware;

    protected function endpoint(): string
    {
        return '/api/completions/1/admin-note';
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

    private function createCompletion(
        int $formatId = FormatConstants::MAPLIST,
        ?string $adminNote = null,
    ): Completion {
        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create(['placement_curver' => 1]);

        $completion = Completion::factory()->create([
            'map_code' => $map->code,
            'admin_note' => $adminNote,
        ]);

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

    #[Group('delete')]
    #[Group('completions')]
    #[Group('admin_note')]
    public function test_without_permission_returns_403(): void
    {
        $completion = $this->createCompletion(FormatConstants::MAPLIST, 'Some note');
        $user = $this->createUserWithPermissions([]);

        $this->actingAs($user, 'discord')
            ->deleteJson("/api/completions/{$completion->id}/admin-note")
            ->assertStatus(403);
    }

    #[Group('delete')]
    #[Group('completions')]
    #[Group('admin_note')]
    public function test_sets_admin_note_to_null(): void
    {
        $completion = $this->createCompletion(FormatConstants::MAPLIST, 'Existing note');
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);

        $this->actingAs($user, 'discord')
            ->deleteJson("/api/completions/{$completion->id}/admin-note")
            ->assertStatus(204);

        $this->actingAs($user, 'discord')
            ->getJson("/api/completions/{$completion->id}?include=admin_note")
            ->assertStatus(200)
            ->assertJsonPath('admin_note', null);
    }

    #[Group('delete')]
    #[Group('completions')]
    #[Group('admin_note')]
    public function test_idempotent_when_already_null(): void
    {
        $completion = $this->createCompletion(FormatConstants::MAPLIST, 'Existing note');
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);

        $this->actingAs($user, 'discord')
            ->deleteJson("/api/completions/{$completion->id}/admin-note")
            ->assertStatus(204);

        $this->actingAs($user, 'discord')
            ->getJson("/api/completions/{$completion->id}?include=admin_note")
            ->assertStatus(200)
            ->assertJsonPath('admin_note', null);

        // Second call: already null, must still return 204
        $this->actingAs($user, 'discord')
            ->deleteJson("/api/completions/{$completion->id}/admin-note")
            ->assertStatus(204);

        $this->actingAs($user, 'discord')
            ->getJson("/api/completions/{$completion->id}?include=admin_note")
            ->assertStatus(200)
            ->assertJsonPath('admin_note', null);
    }

    // ========== NOT FOUND ==========

    #[Group('delete')]
    #[Group('completions')]
    #[Group('admin_note')]
    public function test_nonexistent_completion_returns_404(): void
    {
        $user = $this->createUserWithPermissions([null => ['edit:completion']]);

        $this->actingAs($user, 'discord')
            ->deleteJson('/api/completions/999999/admin-note')
            ->assertStatus(404);
    }
}
