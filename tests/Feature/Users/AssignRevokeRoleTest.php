<?php

namespace Tests\Feature\Users;

use App\Models\Role;
use App\Models\User;
use Tests\TestCase;
use Tests\Traits\TestsDiscordAuthMiddleware;

class AssignRevokeRoleTest extends TestCase
{
    use TestsDiscordAuthMiddleware;

    // PUT /users/{id}/roles/{role_id} — Assign Role
    // DELETE /users/{id}/roles/{role_id} — Revoke Role
    // Actor must hold a role that has a RoleGrant allowing it to grant/revoke the target role.

    protected function endpoint(): string { return '/api/users/111111111111111111/roles/1'; }
    protected function method(): string { return 'PUT'; }

    private function actorWithGrantPermission(Role $targetRole): User
    {
        $actor = User::factory()->create();
        $actorRole = Role::factory()->canGrant([$targetRole->id])->create();
        $actor->roles()->attach($actorRole->id);
        return $actor;
    }

    // === ASSIGN ===

    public function test_assigning_a_role_returns_204_and_user_now_holds_the_role(): void
    {
        $targetRole = Role::factory()->create();
        $actor = $this->actorWithGrantPermission($targetRole);
        $targetUser = User::factory()->create();

        $this->actingAs($actor, 'discord')
            ->putJson("/api/users/{$targetUser->discord_id}/roles/{$targetRole->id}")
            ->assertStatus(204);

        $this->assertTrue($targetUser->roles()->where('roles.id', $targetRole->id)->exists());
    }

    public function test_reassigning_a_role_the_user_already_holds_is_a_noop(): void
    {
        $targetRole = Role::factory()->create();
        $actor = $this->actorWithGrantPermission($targetRole);
        $targetUser = User::factory()->create();
        $targetUser->roles()->attach($targetRole->id);

        $this->actingAs($actor, 'discord')
            ->putJson("/api/users/{$targetUser->discord_id}/roles/{$targetRole->id}")
            ->assertStatus(204);

        $this->assertEquals(1, $targetUser->roles()->where('roles.id', $targetRole->id)->count());
    }

    public function test_assign_actor_has_no_role_that_can_grant_this_role_returns_403(): void
    {
        $targetRole = Role::factory()->create();
        $actor = User::factory()->create(); // no grantable roles
        $targetUser = User::factory()->create();

        // Give actor a role that can't grant targetRole
        $otherRole = Role::factory()->create();
        $actor->roles()->attach($otherRole->id);

        $this->actingAs($actor, 'discord')
            ->putJson("/api/users/{$targetUser->discord_id}/roles/{$targetRole->id}")
            ->assertStatus(403);
    }

    public function test_assign_role_with_no_grantors_defined_returns_403(): void
    {
        $targetRole = Role::factory()->create(); // no grantedBy entries
        $actor = User::factory()->create();
        $targetUser = User::factory()->create();

        $this->actingAs($actor, 'discord')
            ->putJson("/api/users/{$targetUser->discord_id}/roles/{$targetRole->id}")
            ->assertStatus(403);
    }

    public function test_assign_target_user_not_found_returns_404(): void
    {
        $targetRole = Role::factory()->create();
        $actor = $this->actorWithGrantPermission($targetRole);

        $this->actingAs($actor, 'discord')
            ->putJson("/api/users/999999999999999999/roles/{$targetRole->id}")
            ->assertStatus(404);
    }

    public function test_assign_target_role_not_found_returns_404(): void
    {
        $targetRole = Role::factory()->create();
        $actor = $this->actorWithGrantPermission($targetRole);
        $targetUser = User::factory()->create();

        $this->actingAs($actor, 'discord')
            ->putJson("/api/users/{$targetUser->discord_id}/roles/99999")
            ->assertStatus(404);
    }

    public function test_assign_actor_can_grant_role_a_but_not_role_b_returns_403(): void
    {
        $roleA = Role::factory()->create();
        $roleB = Role::factory()->create();
        $actor = $this->actorWithGrantPermission($roleA); // can grant A but not B
        $targetUser = User::factory()->create();

        $this->actingAs($actor, 'discord')
            ->putJson("/api/users/{$targetUser->discord_id}/roles/{$roleB->id}")
            ->assertStatus(403);
    }

    // === REVOKE ===

    public function test_revoking_a_role_returns_204_and_user_no_longer_holds_it(): void
    {
        $targetRole = Role::factory()->create();
        $actor = $this->actorWithGrantPermission($targetRole);
        $targetUser = User::factory()->create();
        $targetUser->roles()->attach($targetRole->id);

        $this->actingAs($actor, 'discord')
            ->deleteJson("/api/users/{$targetUser->discord_id}/roles/{$targetRole->id}")
            ->assertStatus(204);

        $this->assertFalse($targetUser->roles()->where('roles.id', $targetRole->id)->exists());
    }

    public function test_revoking_a_role_the_user_does_not_hold_is_a_noop(): void
    {
        $targetRole = Role::factory()->create();
        $actor = $this->actorWithGrantPermission($targetRole);
        $targetUser = User::factory()->create();

        $this->actingAs($actor, 'discord')
            ->deleteJson("/api/users/{$targetUser->discord_id}/roles/{$targetRole->id}")
            ->assertStatus(204);
    }

    public function test_revoke_actor_has_no_role_that_can_grant_or_revoke_returns_403(): void
    {
        $targetRole = Role::factory()->create();
        $actor = User::factory()->create();
        $targetUser = User::factory()->create();
        $targetUser->roles()->attach($targetRole->id);

        $this->actingAs($actor, 'discord')
            ->deleteJson("/api/users/{$targetUser->discord_id}/roles/{$targetRole->id}")
            ->assertStatus(403);
    }

    public function test_revoke_role_with_no_grantors_defined_returns_403(): void
    {
        $targetRole = Role::factory()->create();
        $actor = User::factory()->create();
        $targetUser = User::factory()->create();
        $targetUser->roles()->attach($targetRole->id);

        $this->actingAs($actor, 'discord')
            ->deleteJson("/api/users/{$targetUser->discord_id}/roles/{$targetRole->id}")
            ->assertStatus(403);
    }

    public function test_revoke_target_user_not_found_returns_404(): void
    {
        $targetRole = Role::factory()->create();
        $actor = $this->actorWithGrantPermission($targetRole);

        $this->actingAs($actor, 'discord')
            ->deleteJson("/api/users/999999999999999999/roles/{$targetRole->id}")
            ->assertStatus(404);
    }

    public function test_revoke_target_role_not_found_returns_404(): void
    {
        $targetRole = Role::factory()->create();
        $actor = $this->actorWithGrantPermission($targetRole);
        $targetUser = User::factory()->create();

        $this->actingAs($actor, 'discord')
            ->deleteJson("/api/users/{$targetUser->discord_id}/roles/99999")
            ->assertStatus(404);
    }
}
