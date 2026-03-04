<?php

namespace Tests\Feature\AchievementRoles;

use App\Models\AchievementRole;
use App\Models\DiscordRole;
use Tests\Traits\TestsDiscordAuthMiddleware;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use TestsDiscordAuthMiddleware;

    protected function endpoint(): string
    {
        return '/api/roles/achievement/1';
    }

    protected function method(): string
    {
        return 'PUT';
    }

    protected function requestData(): array
    {
        return [
            'lb_format' => 1,
            'lb_type' => 'black_border',
            'threshold' => 20,
            'for_first' => false,
            'tooltip_description' => 'Updated description',
            'name' => 'Updated Role Name',
            'clr_border' => 654321,
            'clr_inner' => 210987,
            'discord_roles' => [
                ['guild_id' => '999999999', 'role_id' => '888888888'],
            ],
        ];
    }

    protected function expectedSuccessStatusCode(): int
    {
        return 200;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $role = AchievementRole::factory()->create(['id' => 1, 'lb_format' => 1]);
        DiscordRole::factory()->forAchievementRole($role)->create(['guild_id' => '111', 'role_id' => '222']);
    }

    #[Group('put')]
    #[Group('achievement_roles')]
    public function test_update_achievement_role_requires_permission(): void
    {
        $user = $this->createUserWithPermissions([]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/roles/achievement/1', $this->requestData())
            ->assertStatus(403)
            ->assertJson(['message' => 'Forbidden - Missing edit:achievement_roles permission for this format.']);
    }

    #[Group('put')]
    #[Group('achievement_roles')]
    public function test_update_achievement_role_requires_permission_on_correct_format(): void
    {
        $user = $this->createUserWithPermissions([2 => ['edit:achievement_roles']]); // Has permission on format 2, not format 1

        $this->actingAs($user, 'discord')
            ->putJson('/api/roles/achievement/1', $this->requestData())
            ->assertStatus(403)
            ->assertJson(['message' => 'Forbidden - Missing edit:achievement_roles permission for this format.']);
    }

    #[Group('put')]
    #[Group('achievement_roles')]
    public function test_update_achievement_role_returns_404_if_not_found(): void
    {
        $user = $this->createUserWithPermissions([1 => ['edit:achievement_roles']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/roles/achievement/999999', $this->requestData())
            ->assertStatus(404)
            ->assertJson(['message' => 'Not Found']);
    }

    #[Group('put')]
    #[Group('achievement_roles')]
    public function test_update_achievement_role_syncs_discord_roles_correctly(): void
    {
        $user = $this->createUserWithPermissions([1 => ['edit:achievement_roles']]);

        $actual = $this->actingAs($user, 'discord')
            ->putJson('/api/roles/achievement/1', $this->requestData())
            ->assertStatus(200)
            ->json();

        // Verify via GET endpoint
        $get = $this->getJson('/api/roles/achievement/1')
            ->assertStatus(200)
            ->json();

        $this->assertEquals('999999999', $get['discord_roles'][0]['guild_id']);
        $this->assertEquals('888888888', $get['discord_roles'][0]['role_id']);
        $this->assertCount(1, $get['discord_roles']);
    }

    #[Group('put')]
    #[Group('achievement_roles')]
    public function test_update_achievement_role_fails_on_role_id_collision_with_other_role(): void
    {
        $user = $this->createUserWithPermissions([1 => ['edit:achievement_roles']]);
        $otherRole = AchievementRole::factory()->create(['lb_format' => 1]);
        DiscordRole::factory()->forAchievementRole($otherRole)->create(['role_id' => '555555555']);

        $payload = $this->requestData();
        $payload['discord_roles'] = [
            ['guild_id' => '999', 'role_id' => '555555555'], // Belongs to other role
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/roles/achievement/1', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('discord_roles.0.role_id');
    }

    #[Group('put')]
    #[Group('achievement_roles')]
    public function test_update_achievement_role_successfully_modifies_data(): void
    {
        $user = $this->createUserWithPermissions([1 => ['edit:achievement_roles']]);

        $actual = $this->actingAs($user, 'discord')
            ->putJson('/api/roles/achievement/1', $this->requestData())
            ->assertStatus(200)
            ->json();

        $this->assertEquals(20, $actual['threshold']);
        $this->assertEquals('Updated description', $actual['tooltip_description']);
        $this->assertEquals('Updated Role Name', $actual['name']);
        $this->assertEquals(654321, $actual['clr_border']);
    }

    #[Group('put')]
    #[Group('achievement_roles')]
    public function test_update_achievement_role_allows_same_composite_key_for_current_role(): void
    {
        $user = $this->createUserWithPermissions([1 => ['edit:achievement_roles']]);

        // Update with same values - should succeed
        $payload = [
            'lb_format' => 1,
            'lb_type' => 'black_border',
            'threshold' => 10, // Same as original
            'for_first' => false,
            'name' => 'Same Role',
            'clr_border' => 0,
            'clr_inner' => 0,
            'discord_roles' => [],
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/roles/achievement/1', $payload)
            ->assertStatus(200);
    }

    #[Group('put')]
    #[Group('achievement_roles')]
    public function test_update_achievement_role_requires_all_fields(): void
    {
        $user = $this->createUserWithPermissions([1 => ['edit:achievement_roles']]);

        $actual = $this->actingAs($user, 'discord')
            ->putJson('/api/roles/achievement/1', [])
            ->assertStatus(422)
            ->json();

        $expectedKeys = ['lb_format', 'lb_type', 'threshold', 'for_first', 'name', 'clr_border', 'clr_inner'];
        $actualKeys = array_keys($actual['errors']);
        $this->assertEquals($expectedKeys, $actualKeys);
    }
}
