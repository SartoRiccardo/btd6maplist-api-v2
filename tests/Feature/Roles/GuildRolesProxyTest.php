<?php

namespace Tests\Feature\Roles;

use App\Constants\FormatConstants;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\TestsDiscordAuthMiddleware;

class GuildRolesProxyTest extends TestCase
{
    use TestsDiscordAuthMiddleware;

    // GET /proxy/discord/guilds/{id}/roles
    // Proxies to Discord API v10 using bot token. Requires Discord auth + edit:achievement_roles on any format.

    private string $guildId = '123456789012345678';

    protected function endpoint(): string { return "/api/proxy/discord/guilds/{$this->guildId}/roles"; }
    protected function method(): string { return 'GET'; }

    private function fakeDiscord(array $body = [], int $status = 200): void
    {
        Http::fake([
            'https://discord.com/api/v10/guilds/*' => Http::response($body, $status),
        ]);
    }

    public function test_authenticated_user_with_permission_gets_discord_role_list_with_200(): void
    {
        $this->fakeDiscord([['id' => '111', 'name' => 'Admin']]);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:achievement_roles']]);

        $this->actingAs($user, 'discord')
            ->getJson("/api/proxy/discord/guilds/{$this->guildId}/roles")
            ->assertStatus(200);
    }

    public function test_response_body_is_exactly_what_discord_returned(): void
    {
        $discordData = [['id' => '999', 'name' => 'Moderator', 'color' => 0]];
        $this->fakeDiscord($discordData);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:achievement_roles']]);

        $actual = $this->actingAs($user, 'discord')
            ->getJson("/api/proxy/discord/guilds/{$this->guildId}/roles")
            ->assertStatus(200)
            ->json();

        $this->assertEquals($discordData, $actual);
    }

    public function test_authenticated_but_no_edit_achievement_roles_on_any_format_returns_403(): void
    {
        $this->fakeDiscord();
        $user = $this->createUserWithPermissions([]);

        $this->actingAs($user, 'discord')
            ->getJson("/api/proxy/discord/guilds/{$this->guildId}/roles")
            ->assertStatus(403);
    }

    public function test_discord_api_returns_404_proxy_passes_through_404(): void
    {
        $this->fakeDiscord(['message' => 'Unknown Guild'], 404);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:achievement_roles']]);

        $this->actingAs($user, 'discord')
            ->getJson("/api/proxy/discord/guilds/{$this->guildId}/roles")
            ->assertStatus(404);
    }

    public function test_discord_api_returns_403_proxy_passes_through_403(): void
    {
        $this->fakeDiscord(['message' => 'Missing Access'], 403);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:achievement_roles']]);

        $this->actingAs($user, 'discord')
            ->getJson("/api/proxy/discord/guilds/{$this->guildId}/roles")
            ->assertStatus(403);
    }

    public function test_discord_api_returns_500_proxy_passes_through_500(): void
    {
        $this->fakeDiscord(['message' => 'Internal Server Error'], 500);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:achievement_roles']]);

        $this->actingAs($user, 'discord')
            ->getJson("/api/proxy/discord/guilds/{$this->guildId}/roles")
            ->assertStatus(500);
    }

    public function test_user_with_edit_achievement_roles_on_at_least_one_format_access_granted(): void
    {
        $this->fakeDiscord([['id' => '1']]);
        // Permission only on MAPLIST, not global
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:achievement_roles']]);

        $this->actingAs($user, 'discord')
            ->getJson("/api/proxy/discord/guilds/{$this->guildId}/roles")
            ->assertStatus(200);
    }
}
