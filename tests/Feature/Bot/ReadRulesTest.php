<?php

namespace Tests\Feature\Bot;

use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use Tests\Traits\TestsBotAuth;

class ReadRulesTest extends TestCase
{
    use TestsBotAuth;

    protected function endpoint(): string
    {
        return '/bot/read-rules';
    }

    protected function method(): string
    {
        return 'PUT';
    }

    #[Group('put')]
    #[Group('bot')]
    #[Group('read-rules')]
    public function test_reading_rules_sets_has_seen_popup_to_true(): void
    {
        $this->makeBotSignedRequest()
            ->assertStatus(204);

        $this->assertDatabaseHas('users', [
            'discord_id' => self::BOT_USER_ID,
            'has_seen_popup' => true,
        ]);
    }

    #[Group('put')]
    #[Group('bot')]
    #[Group('read-rules')]
    public function test_reading_rules_twice_is_idempotent(): void
    {
        $this->makeBotSignedRequest()->assertStatus(204);
        $this->makeBotSignedRequest()->assertStatus(204);

        $this->assertDatabaseHas('users', [
            'discord_id' => self::BOT_USER_ID,
            'has_seen_popup' => true,
        ]);

        $this->assertEquals(1, User::where('discord_id', self::BOT_USER_ID)->count());
    }

    #[Group('put')]
    #[Group('bot')]
    #[Group('read-rules')]
    public function test_missing_user_payload_returns_422(): void
    {
        $this->makeBotSignedRequest(body: [])
            ->assertStatus(422)
            ->assertJson(['error' => 'Missing or invalid _user payload']);
    }

    #[Group('put')]
    #[Group('bot')]
    #[Group('read-rules')]
    public function test_incomplete_user_payload_returns_422(): void
    {
        $this->makeBotSignedRequest(body: ['_user' => ['discord_id' => self::BOT_USER_ID]])
            ->assertStatus(422)
            ->assertJson(['error' => 'Missing or invalid _user payload']);
    }
}
