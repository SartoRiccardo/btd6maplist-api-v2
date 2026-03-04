<?php

namespace Tests\Feature\AchievementRoles;

use App\Models\AchievementRole;
use App\Models\DiscordRole;
use Tests\Traits\TestsDiscordAuthMiddleware;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use TestsDiscordAuthMiddleware;

    protected function endpoint(): string
    {
        return '/api/roles/achievement';
    }

    protected function method(): string
    {
        return 'POST';
    }

    protected function requestData(): array
    {
        return [
            'lb_format' => 1,
            'lb_type' => 'black_border',
            'threshold' => 10,
            'for_first' => false,
            'tooltip_description' => 'Test description',
            'name' => 'Test Achievement Role',
            'clr_border' => 123456,
            'clr_inner' => 789012,
            'discord_roles' => [
                ['guild_id' => '123456789', 'role_id' => '987654321'],
            ],
        ];
    }

    protected function expectedSuccessStatusCode(): int
    {
        return 201;
    }

    #[Group('store')]
    #[Group('achievement_roles')]
    public function test_create_achievement_role_requires_permission(): void
    {
        $user = $this->createUserWithPermissions([]);

        $this->actingAs($user, 'discord')
            ->postJson('/api/roles/achievement', $this->requestData())
            ->assertStatus(403)
            ->assertJson(['message' => 'Forbidden - Missing edit:achievement_roles permission for this format.']);
    }

    #[Group('store')]
    #[Group('achievement_roles')]
    public function test_create_achievement_role_requires_permission_on_correct_format(): void
    {
        $user = $this->createUserWithPermissions([2 => ['edit:achievement_roles']]); // Has permission on format 2, not format 1

        $this->actingAs($user, 'discord')
            ->postJson('/api/roles/achievement', $this->requestData())
            ->assertStatus(403)
            ->assertJson(['message' => 'Forbidden - Missing edit:achievement_roles permission for this format.']);
    }

    #[Group('store')]
    #[Group('achievement_roles')]
    public function test_create_achievement_role_requires_all_fields(): void
    {
        $user = $this->createUserWithPermissions([1 => ['edit:achievement_roles']]);

        $actual = $this->actingAs($user, 'discord')
            ->postJson('/api/roles/achievement', [])
            ->assertStatus(422)
            ->json();

        $expectedKeys = ['lb_format', 'lb_type', 'threshold', 'for_first', 'name', 'clr_border', 'clr_inner'];
        $this->assertEquals($expectedKeys, array_keys($actual['errors']));
    }

    #[Group('store')]
    #[Group('achievement_roles')]
    public function test_create_achievement_role_fails_on_duplicate_uniqueness_rules(): void
    {
        $user = $this->createUserWithPermissions([1 => ['edit:achievement_roles']]);
        AchievementRole::factory()->create([
            'lb_format' => 1,
            'lb_type' => 'black_border',
            'threshold' => 10,
        ]);

        $actual = $this->actingAs($user, 'discord')
            ->postJson('/api/roles/achievement', $this->requestData())
            ->assertStatus(422)
            ->json();

        $this->assertEquals(['threshold'], array_keys($actual['errors']));
    }

    #[Group('store')]
    #[Group('achievement_roles')]
    public function test_create_achievement_role_fails_on_duplicate_for_first(): void
    {
        $user = $this->createUserWithPermissions([1 => ['edit:achievement_roles']]);
        AchievementRole::factory()->create([
            'lb_format' => 1,
            'lb_type' => 'black_border',
            'threshold' => 5,
            'for_first' => true,
        ]);

        $payload = $this->requestData();
        $payload['threshold'] = 15;
        $payload['for_first'] = true;

        $actual = $this->actingAs($user, 'discord')
            ->postJson('/api/roles/achievement', $payload)
            ->assertStatus(422)
            ->json();

        $this->assertEquals(['for_first'], array_keys($actual['errors']));
    }

    #[Group('store')]
    #[Group('achievement_roles')]
    public function test_create_achievement_role_fails_on_duplicate_role_id_in_payload(): void
    {
        $user = $this->createUserWithPermissions([1 => ['edit:achievement_roles']]);

        $payload = $this->requestData();
        $payload['discord_roles'] = [
            ['guild_id' => '123', 'role_id' => '456'],
            ['guild_id' => '789', 'role_id' => '456'], // Duplicate role_id
        ];

        $actual = $this->actingAs($user, 'discord')
            ->postJson('/api/roles/achievement', $payload)
            ->assertStatus(422)
            ->json();

        $expectedKeys = ['discord_roles.0.role_id', 'discord_roles.1.role_id'];
        $this->assertEquals($expectedKeys, array_keys($actual['errors']));
    }

    #[Group('store')]
    #[Group('achievement_roles')]
    public function test_create_achievement_role_fails_on_role_id_already_in_database(): void
    {
        $user = $this->createUserWithPermissions([1 => ['edit:achievement_roles']]);
        $existingRole = AchievementRole::factory()->create();
        DiscordRole::factory()->forAchievementRole($existingRole)->create(['role_id' => '111111111']);

        $payload = $this->requestData();
        $payload['discord_roles'] = [
            ['guild_id' => '123', 'role_id' => '111111111'], // Already exists
        ];

        $actual = $this->actingAs($user, 'discord')
            ->postJson('/api/roles/achievement', $payload)
            ->assertStatus(422)
            ->json();

        $this->assertEquals(['discord_roles.0.role_id'], array_keys($actual['errors']));
    }

    #[Group('store')]
    #[Group('achievement_roles')]
    public function test_create_achievement_role_with_discord_roles_successfully_stores_all_data(): void
    {
        $user = $this->createUserWithPermissions([1 => ['edit:achievement_roles']]);

        $actual = $this->actingAs($user, 'discord')
            ->postJson('/api/roles/achievement', $this->requestData())
            ->assertStatus(201)
            ->json();

        $roleId = $actual['id'];

        // Verify via GET endpoint
        $get = $this->getJson("/api/roles/achievement/{$roleId}")
            ->assertStatus(200)
            ->json();

        $this->assertEquals('123456789', $get['discord_roles'][0]['guild_id']);
        $this->assertEquals('987654321', $get['discord_roles'][0]['role_id']);
    }
}
