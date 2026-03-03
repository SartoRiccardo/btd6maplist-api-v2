<?php

namespace Tests\Feature\Users;

use App\Models\Role;
use App\Models\User;
use Tests\Traits\TestsDiscordAuthMiddleware;
use Tests\TestCase;

class BanTest extends TestCase
{
    use TestsDiscordAuthMiddleware;

    protected function endpoint(): string
    {
        return '/api/users/123456789012345678/ban';
    }

    protected function method(): string
    {
        return 'PUT';
    }

    // ========== AUTHORIZATION TESTS ==========

    public function test_ban_requires_global_ban_user_permission(): void
    {
        $targetUser = User::factory()->create();

        // User with format-restricted ban:user permission (should fail)
        $user = $this->createUserWithPermissions([1 => ['ban:user']]);

        $this->actingAs($user, 'discord')
            ->putJson("/api/users/{$targetUser->discord_id}/ban")
            ->assertStatus(403)
            ->assertJson(['message' => 'Forbidden - You do not have permission to ban users.']);
    }

    public function test_ban_without_permission_returns_403(): void
    {
        $targetUser = User::factory()->create();
        $user = $this->createUserWithPermissions([]);

        $this->actingAs($user, 'discord')
            ->putJson("/api/users/{$targetUser->discord_id}/ban")
            ->assertStatus(403)
            ->assertJson(['message' => 'Forbidden - You do not have permission to ban users.']);
    }

    public function test_ban_user_removes_assign_on_create_roles(): void
    {
        // Get assign_on_create role IDs (7 = CAN_SUBMIT, 14 = BASIC_PERMS from RoleSeeder)
        $assignOnCreateRoleIds = Role::where('assign_on_create', true)->pluck('id')->toArray();
        $nonAssignOnCreateRoleIds = Role::where('assign_on_create', false)
            ->whereNotIn('id', $assignOnCreateRoleIds)
            ->take(1)
            ->pluck('id')
            ->toArray();

        // Create user with both types of roles
        $targetUser = User::factory()->create();
        $targetUser->roles()->attach(array_merge($assignOnCreateRoleIds, $nonAssignOnCreateRoleIds));

        $user = $this->createUserWithPermissions([null => ['ban:user']]);

        // Verify initial state
        $initialRoleCount = count($assignOnCreateRoleIds) + count($nonAssignOnCreateRoleIds);
        $this->assertCount($initialRoleCount, $targetUser->roles);

        $this->actingAs($user, 'discord')
            ->putJson("/api/users/{$targetUser->discord_id}/ban")
            ->assertStatus(204);

        // Verify ban status and roles via GET request
        $response = $this->getJson("/api/users/{$targetUser->discord_id}")
            ->assertStatus(200)
            ->assertJson(['is_banned' => true]);

        $roles = $response->json('roles');
        $roleIds = array_column($roles, 'id');

        // Should only have non-assign_on_create roles
        $this->assertCount(count($nonAssignOnCreateRoleIds), $roleIds);

        foreach ($assignOnCreateRoleIds as $removedRoleId) {
            $this->assertNotContains($removedRoleId, $roleIds);
        }
        foreach ($nonAssignOnCreateRoleIds as $keptRoleId) {
            $this->assertContains($keptRoleId, $roleIds);
        }
    }

    public function test_ban_is_idempotent(): void
    {
        $targetUser = User::factory()->create(['is_banned' => false]);
        $user = $this->createUserWithPermissions([null => ['ban:user']]);

        // First ban
        $this->actingAs($user, 'discord')
            ->putJson("/api/users/{$targetUser->discord_id}/ban")
            ->assertStatus(204);

        $firstResponse = $this->getJson("/api/users/{$targetUser->discord_id}")
            ->assertStatus(200);

        // Second ban should not cause error
        $this->actingAs($user, 'discord')
            ->putJson("/api/users/{$targetUser->discord_id}/ban")
            ->assertStatus(204);

        $secondResponse = $this->getJson("/api/users/{$targetUser->discord_id}")
            ->assertStatus(200);

        // Idempotent: responses should be identical
        $this->assertEquals($firstResponse->json(), $secondResponse->json());
    }

    public function test_ban_returns_404_if_user_not_found(): void
    {
        $user = $this->createUserWithPermissions([null => ['ban:user']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/users/999999999999999999/ban')
            ->assertStatus(404)
            ->assertJson(['message' => 'Not Found']);
    }
}
