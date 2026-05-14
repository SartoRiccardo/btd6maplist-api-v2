<?php

namespace Tests\Feature\Completions\List;

use App\Constants\FormatConstants;
use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\User;
use PHPUnit\Metadata\Group;
use Tests\TestCase;

class AdminNoteIncludeTest extends TestCase
{
    // GET /completions?include=admin_note
    // admin_note is returned only when the authenticated user has edit:completion on the completion's format.
    // Silently ignored for unauthenticated requests or insufficient permissions.

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

    #[Group('get')]
    #[Group('completions')]
    #[Group('admin_note')]
    public function test_admin_note_absent_without_include_param(): void
    {
        $this->createCompletion(FormatConstants::MAPLIST, 'Secret note');

        $data = $this->getJson('/api/completions?pending=any')
            ->assertStatus(200)
            ->json('data');

        $this->assertArrayNotHasKey('admin_note', $data[0]);
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('admin_note')]
    public function test_admin_note_silently_ignored_when_unauthenticated(): void
    {
        $this->createCompletion(FormatConstants::MAPLIST, 'Secret note');

        $data = $this->getJson('/api/completions?include=admin_note&pending=any')
            ->assertStatus(200)
            ->json('data');

        $this->assertArrayNotHasKey('admin_note', $data[0]);
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('admin_note')]
    public function test_admin_note_silently_ignored_without_edit_permission(): void
    {
        $this->createCompletion(FormatConstants::MAPLIST, 'Secret note');
        $user = User::factory()->create();

        $data = $this->actingAs($user, 'discord')
            ->getJson('/api/completions?include=admin_note&pending=any')
            ->assertStatus(200)
            ->json('data');

        $this->assertArrayNotHasKey('admin_note', $data[0]);
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('admin_note')]
    public function test_admin_note_silently_ignored_with_wrong_format_permission(): void
    {
        $this->createCompletion(FormatConstants::EXPERT_LIST, 'Secret note');
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);

        $data = $this->actingAs($user, 'discord')
            ->getJson('/api/completions?include=admin_note&pending=any')
            ->assertStatus(200)
            ->json('data');

        $this->assertArrayNotHasKey('admin_note', $data[0]);
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('admin_note')]
    public function test_admin_note_included_with_correct_format_permission(): void
    {
        $this->createCompletion(FormatConstants::MAPLIST, 'Visible note');
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);

        $data = $this->actingAs($user, 'discord')
            ->getJson('/api/completions?include=admin_note&pending=any')
            ->assertStatus(200)
            ->json('data');

        $this->assertArrayHasKey('admin_note', $data[0]);
        $this->assertEquals('Visible note', $data[0]['admin_note']);
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('admin_note')]
    public function test_admin_note_null_when_not_set(): void
    {
        $this->createCompletion(FormatConstants::MAPLIST, null);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);

        $data = $this->actingAs($user, 'discord')
            ->getJson('/api/completions?include=admin_note&pending=any')
            ->assertStatus(200)
            ->json('data');

        $this->assertArrayHasKey('admin_note', $data[0]);
        $this->assertNull($data[0]['admin_note']);
    }
}
