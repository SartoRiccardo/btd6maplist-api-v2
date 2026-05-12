<?php

namespace Tests\Feature\Completions;

use App\Constants\FormatConstants;
use App\Jobs\UpdateCompletionWebhookJob;
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

class BotRejectCompletionTest extends TestCase
{
    use TestsBotAuth;

    protected function endpoint(): string
    {
        return '/bot/completions/reject';
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

    private function createPendingCompletion(int $formatId = FormatConstants::MAPLIST): Completion
    {
        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create(['placement_curver' => 1]);

        $completion = Completion::factory()->create([
            'map_code' => $map->code,
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

    // ========== VALIDATION ==========

    #[Group('post')]
    #[Group('bot')]
    #[Group('completions')]
    public function test_missing_webhook_message_id_returns_422(): void
    {
        $this->makeBotSignedRequest(['_user' => [
            'discord_id' => self::BOT_USER_ID,
            'name' => self::BOT_USERNAME,
        ]])
            ->assertStatus(422)
            ->assertJson(['message' => 'Missing completion_webhook_message_id.']);
    }

    // ========== NOT FOUND ==========

    #[Group('post')]
    #[Group('bot')]
    #[Group('completions')]
    public function test_unknown_webhook_message_id_returns_404(): void
    {
        $this->makeBotSignedRequest()
            ->assertStatus(404);
    }

    // ========== BUSINESS RULES ==========

    #[Group('post')]
    #[Group('bot')]
    #[Group('completions')]
    public function test_already_accepted_completion_returns_422(): void
    {
        $completion = $this->createPendingCompletion();
        CompletionMeta::where('completion_id', $completion->id)
            ->update(['accepted_by_id' => '111111111111111111']);

        $this->makeBotSignedRequest()
            ->assertStatus(422)
            ->assertJson(['message' => 'Completion is not pending.']);
    }

    #[Group('post')]
    #[Group('bot')]
    #[Group('completions')]
    public function test_bot_user_without_edit_completion_permission_returns_403(): void
    {
        $this->createPendingCompletion(FormatConstants::MAPLIST);

        User::factory()->create(['discord_id' => self::BOT_USER_ID]);

        $this->makeBotSignedRequest()
            ->assertStatus(403)
            ->assertJson(['message' => 'Forbidden - You do not have permission to reject completions for this format.']);
    }

    // ========== HAPPY PATH ==========

    #[Group('post')]
    #[Group('bot')]
    #[Group('completions')]
    public function test_reject_sets_deleted_on_and_dispatches_job(): void
    {
        $completion = $this->createPendingCompletion(FormatConstants::MAPLIST);

        $botUser = User::factory()->create(['discord_id' => self::BOT_USER_ID]);
        $role = Role::factory()->create();
        $botUser->roles()->attach($role->id);
        RoleFormatPermission::factory()
            ->for($role)
            ->permission('edit:completion', FormatConstants::MAPLIST)
            ->create();

        $this->makeBotSignedRequest()
            ->assertStatus(204);

        $meta = CompletionMeta::where('completion_id', $completion->id)->first();
        $this->assertNotNull($meta->deleted_on);

        Bus::assertDispatched(UpdateCompletionWebhookJob::class, function ($job) use ($completion) {
            return $job->completionId === $completion->id && $job->fail === true;
        });
    }
}
