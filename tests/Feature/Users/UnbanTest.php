<?php

namespace Tests\Feature\Users;

use App\Models\Role;
use App\Models\User;
use Tests\Traits\TestsDiscordAuthMiddleware;
use Tests\TestCase;

class UnbanTest extends TestCase
{
    use TestsDiscordAuthMiddleware;

    protected function endpoint(): string
    {
        return '/api/users/123456789012345678/unban';
    }

    protected function method(): string
    {
        return 'PUT';
    }

    // ========== AUTHORIZATION TESTS ==========

    public function test_unban_requires_global_ban_user_permission(): void
    {
        $targetUser = User::factory()->create();

        // User with format-restricted ban:user permission (should fail)
        $user = $this->createUserWithPermissions([1 => ['ban:user']]);

        $this->actingAs($user, 'discord')
            ->putJson("/api/users/{$targetUser->discord_id}/unban")
            ->assertStatus(403)
            ->assertJson(['message' => 'Forbidden - You do not have permission to unban users.']);
    }

    public function test_unban_without_permission_returns_403(): void
    {
        $targetUser = User::factory()->create();
        $user = $this->createUserWithPermissions([]);

        $this->actingAs($user, 'discord')
            ->putJson("/api/users/{$targetUser->discord_id}/unban")
            ->assertStatus(403)
            ->assertJson(['message' => 'Forbidden - You do not have permission to unban users.']);
    }

    // ========== FUNCTIONAL TESTS ==========

    public function test_unban_user_restores_assign_on_create_roles(): void
    {
        // Get assign_on_create role IDs (7 = CAN_SUBMIT, 14 = BASIC_PERMS from RoleSeeder)
        $assignOnCreateRoleIds = Role::where('assign_on_create', true)->pluck('id')->toArray();

        // Create banned user with no roles
        $targetUser = User::factory()->create(['is_banned' => true]);

        $user = $this->createUserWithPermissions([null => ['ban:user']]);

        // Verify initial state
        $this->assertCount(0, $targetUser->roles);

        $this->actingAs($user, 'discord')
            ->putJson("/api/users/{$targetUser->discord_id}/unban")
            ->assertStatus(204);

        // Verify unban status and roles via GET request
        $response = $this->getJson("/api/users/{$targetUser->discord_id}")
            ->assertStatus(200)
            ->assertJson(['is_banned' => false]);

        $roles = $response->json('roles');
        $roleIds = array_column($roles, 'id');

        // Should have all assign_on_create roles
        $this->assertCount(count($assignOnCreateRoleIds), $roleIds);

        foreach ($assignOnCreateRoleIds as $restoredRoleId) {
            $this->assertContains($restoredRoleId, $roleIds);
        }
    }

    public function test_unban_is_idempotent(): void
    {
        $targetUser = User::factory()->create(['is_banned' => true]);
        $user = $this->createUserWithPermissions([null => ['ban:user']]);

        // First unban
        $this->actingAs($user, 'discord')
            ->putJson("/api/users/{$targetUser->discord_id}/unban")
            ->assertStatus(204);

        $firstResponse = $this->getJson("/api/users/{$targetUser->discord_id}")
            ->assertStatus(200);

        // Second unban should not cause error
        $this->actingAs($user, 'discord')
            ->putJson("/api/users/{$targetUser->discord_id}/unban")
            ->assertStatus(204);

        $secondResponse = $this->getJson("/api/users/{$targetUser->discord_id}")
            ->assertStatus(200);

        // Idempotent: responses should be identical
        $this->assertEquals($firstResponse->json(), $secondResponse->json());
    }

    public function test_unban_returns_404_if_user_not_found(): void
    {
        $user = $this->createUserWithPermissions([null => ['ban:user']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/users/999999999999999999/unban')
            ->assertStatus(404)
            ->assertJson(['message' => 'Not Found']);
    }
}
