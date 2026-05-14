<?php

namespace Tests\Feature\Completions;

use App\Constants\FormatConstants;
use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\Role;
use App\Models\RoleFormatPermission;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Metadata\Group;
use Tests\TestCase;
use Tests\Traits\TestsBotAuth;

class BotAdminNoteTest extends TestCase
{
    use TestsBotAuth;

    protected function endpoint(): string
    {
        return '/bot/completions/accept';
    }

    protected function method(): string
    {
        return 'POST';
    }

    protected function botRequestData(): array
    {
        return ['completion_webhook_message_id' => '999888777666555444'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
    }

    private function createPendingCompletion(
        int $formatId = FormatConstants::MAPLIST,
        ?string $adminNote = null,
    ): Completion {
        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create(['placement_curver' => 1]);

        $completion = Completion::factory()->create([
            'map_code' => $map->code,
            'admin_note' => $adminNote,
            'wh_msg_id' => '999888777666555444',
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

    private function createBotUserWithPermission(int $formatId = FormatConstants::MAPLIST): User
    {
        $botUser = User::factory()->create(['discord_id' => self::BOT_USER_ID]);
        $role = Role::factory()->create();
        $botUser->roles()->attach($role->id);
        RoleFormatPermission::factory()
            ->for($role)
            ->permission('edit:completion', $formatId)
            ->create();

        return $botUser;
    }

    // ============================================================
    // POST /bot/completions/accept blocked by admin_note
    // ============================================================

    #[Group('post')]
    #[Group('bot')]
    #[Group('completions')]
    #[Group('admin_note')]
    public function test_bot_accept_with_admin_note_returns_422_with_note_as_message(): void
    {
        $this->createPendingCompletion(FormatConstants::MAPLIST, 'Flagged: suspicious account');
        $this->createBotUserWithPermission(FormatConstants::MAPLIST);

        $this->makeBotSignedRequest()
            ->assertStatus(422)
            ->assertJson(['message' => 'Flagged: suspicious account']);
    }

    #[Group('post')]
    #[Group('bot')]
    #[Group('completions')]
    #[Group('admin_note')]
    public function test_bot_accept_without_admin_note_succeeds(): void
    {
        $completion = $this->createPendingCompletion(FormatConstants::MAPLIST, null);
        $this->createBotUserWithPermission(FormatConstants::MAPLIST);

        $this->makeBotSignedRequest()
            ->assertStatus(204);

        $meta = CompletionMeta::where('completion_id', $completion->id)->first();
        $this->assertEquals(self::BOT_USER_ID, $meta->accepted_by_id);
    }

    #[Group('post')]
    #[Group('bot')]
    #[Group('completions')]
    #[Group('admin_note')]
    public function test_bot_accept_unblocked_after_admin_note_cleared(): void
    {
        $completion = $this->createPendingCompletion(FormatConstants::MAPLIST, 'Temp block');
        $this->createBotUserWithPermission(FormatConstants::MAPLIST);

        // Blocked while note exists
        $this->makeBotSignedRequest()
            ->assertStatus(422);

        // Clear the note directly
        $completion->admin_note = null;
        $completion->save();

        // Bot accept now succeeds
        $this->makeBotSignedRequest()
            ->assertStatus(204);

        $meta = CompletionMeta::where('completion_id', $completion->id)->first();
        $this->assertEquals(self::BOT_USER_ID, $meta->accepted_by_id);
    }
}
