<?php

namespace Tests\Feature\Users;

use Tests\TestCase;

class AssignRevokeRoleTest extends TestCase
{
    // PUT /users/{id}/roles/{role_id} — Assign Role
    // DELETE /users/{id}/roles/{role_id} — Revoke Role

    public function test_assigning_a_role_returns_204_and_user_now_holds_the_role(): void
    {
        $this->markTestSkipped('Assigning a role returns 204 and user now holds the role');
    }

    public function test_reassigning_a_role_the_user_already_holds_is_a_noop(): void
    {
        $this->markTestSkipped('Re-assigning a role the user already holds is a no-op (204, no duplicate pivot row)');
    }

    public function test_assign_unauthenticated_returns_401(): void
    {
        $this->markTestSkipped('Unauthenticated assign returns 401');
    }

    public function test_assign_actor_has_no_role_that_can_grant_this_role_returns_403(): void
    {
        $this->markTestSkipped('Actor has no role that can grant this role → 403');
    }

    public function test_assign_role_with_no_grantors_defined_returns_403(): void
    {
        $this->markTestSkipped('Role has no grantors defined at all → 403 (empty grantedBy means nobody can grant it)');
    }

    public function test_assign_target_user_not_found_returns_404(): void
    {
        $this->markTestSkipped('Target user not found → 404');
    }

    public function test_assign_target_role_not_found_returns_404(): void
    {
        $this->markTestSkipped('Target role not found → 404');
    }

    public function test_assign_actor_can_grant_role_a_but_not_role_b_returns_403(): void
    {
        $this->markTestSkipped('Actor can grant role A but not role B — assigning B returns 403');
    }

    public function test_revoking_a_role_returns_204_and_user_no_longer_holds_it(): void
    {
        $this->markTestSkipped('Revoking a role returns 204 and user no longer holds it');
    }

    public function test_revoking_a_role_the_user_does_not_hold_is_a_noop(): void
    {
        $this->markTestSkipped('Revoking a role the user does not hold is a no-op (204, no error)');
    }

    public function test_revoke_unauthenticated_returns_401(): void
    {
        $this->markTestSkipped('Unauthenticated revoke returns 401');
    }

    public function test_revoke_actor_has_no_role_that_can_grant_or_revoke_returns_403(): void
    {
        $this->markTestSkipped('Actor has no role that can grant (and therefore revoke) this role → 403');
    }

    public function test_revoke_role_with_no_grantors_defined_returns_403(): void
    {
        $this->markTestSkipped('Role has no grantors defined at all → 403');
    }

    public function test_revoke_target_user_not_found_returns_404(): void
    {
        $this->markTestSkipped('Target user not found → 404');
    }

    public function test_revoke_target_role_not_found_returns_404(): void
    {
        $this->markTestSkipped('Target role not found → 404');
    }
}
