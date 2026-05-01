<?php

namespace Tests\Feature\Roles;

use Tests\TestCase;

class GuildRolesProxyTest extends TestCase
{
    // GET /proxy/discord/guilds/{id}/roles
    // Proxies to Discord API v10 using bot token. Requires Discord auth + edit:achievement_roles on any format.

    public function test_authenticated_user_with_permission_gets_discord_role_list_with_200(): void
    {
        $this->markTestSkipped('Authenticated user with permission gets Discord\'s role list passed through with 200');
    }

    public function test_response_body_is_exactly_what_discord_returned(): void
    {
        $this->markTestSkipped('Response body is exactly what Discord returned — no transformation');
    }

    public function test_unauthenticated_returns_401(): void
    {
        $this->markTestSkipped('Unauthenticated returns 401');
    }

    public function test_authenticated_but_no_edit_achievement_roles_on_any_format_returns_403(): void
    {
        $this->markTestSkipped('Authenticated but no edit:achievement_roles on any format → 403');
    }

    public function test_discord_api_returns_404_proxy_passes_through_404(): void
    {
        $this->markTestSkipped('Discord API returns 404 (guild not found) → proxy passes through 404');
    }

    public function test_discord_api_returns_403_proxy_passes_through_403(): void
    {
        $this->markTestSkipped('Discord API returns 403 (bot not in guild) → proxy passes through 403');
    }

    public function test_discord_api_returns_500_proxy_passes_through_500(): void
    {
        $this->markTestSkipped('Discord API returns 500 → proxy passes through 500');
    }

    public function test_user_with_edit_achievement_roles_on_at_least_one_format_access_granted(): void
    {
        $this->markTestSkipped('User has edit:achievement_roles on at least one format (not all) → access granted');
    }
}
