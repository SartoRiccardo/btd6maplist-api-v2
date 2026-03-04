<?php

namespace Tests\Feature\AchievementRoles;

use App\Models\AchievementRole;
use App\Models\DiscordRole;
use Tests\Traits\TestsDiscordAuthMiddleware;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use TestsDiscordAuthMiddleware;

    protected function endpoint(): string
    {
        return '/api/roles/achievement/1';
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

    protected function setUp(): void
    {
        parent::setUp();
        $role = AchievementRole::factory()->create(['id' => 1, 'lb_format' => 1]);
        DiscordRole::factory()->forAchievementRole($role)->create(['guild_id' => '111', 'role_id' => '222']);
        DiscordRole::factory()->forAchievementRole($role)->create(['guild_id' => '333', 'role_id' => '444']);
    }

    #[Group('delete')]
    #[Group('achievement_roles')]
    public function test_delete_achievement_role_requires_permission(): void
    {
        $user = $this->createUserWithPermissions([]);

        $this->actingAs($user, 'discord')
            ->deleteJson('/api/roles/achievement/1')
            ->assertStatus(403)
            ->assertJson(['message' => 'Forbidden - Missing edit:achievement_roles permission for this format.']);
    }

    #[Group('delete')]
    #[Group('achievement_roles')]
    public function test_delete_achievement_role_requires_permission_on_correct_format(): void
    {
        $user = $this->createUserWithPermissions([2 => ['edit:achievement_roles']]); // Has permission on format 2, not format 1

        $this->actingAs($user, 'discord')
            ->deleteJson('/api/roles/achievement/1')
            ->assertStatus(403)
            ->assertJson(['message' => 'Forbidden - Missing edit:achievement_roles permission for this format.']);
    }

    #[Group('delete')]
    #[Group('achievement_roles')]
    public function test_delete_achievement_role_returns_404_if_not_found(): void
    {
        $user = $this->createUserWithPermissions([1 => ['edit:achievement_roles']]);

        $this->actingAs($user, 'discord')
            ->deleteJson('/api/roles/achievement/999999')
            ->assertStatus(404)
            ->assertJson(['message' => 'Not Found']);
    }

    #[Group('delete')]
    #[Group('achievement_roles')]
    public function test_delete_achievement_role_removes_record_and_associated_discord_roles(): void
    {
        $user = $this->createUserWithPermissions([1 => ['edit:achievement_roles']]);

        // Verify records exist
        $this->assertDatabaseHas('achievement_roles', ['id' => 1]);
        $this->assertEquals(2, DiscordRole::count());

        $this->actingAs($user, 'discord')
            ->deleteJson('/api/roles/achievement/1')
            ->assertStatus(204)
            ->assertNoContent();

        // Verify cascade delete worked
        $this->assertDatabaseMissing('achievement_roles', ['id' => 1]);
        $this->assertEquals(0, DiscordRole::count());
    }
}
