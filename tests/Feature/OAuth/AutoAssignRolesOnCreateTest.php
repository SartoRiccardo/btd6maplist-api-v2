<?php

namespace Tests\Feature\OAuth;

use Tests\TestCase;

class AutoAssignRolesOnCreateTest extends TestCase
{
    // On first OAuth login, all roles with assign_on_create=true are synced to the new user.

    public function test_new_user_gets_assign_on_create_roles(): void
    {
        $this->markTestSkipped('New user gets assign_on_create roles — log in with a fresh Discord ID; user is created and holds all assign_on_create roles');
    }

    public function test_multiple_assign_on_create_roles_all_assigned(): void
    {
        $this->markTestSkipped('Multiple assign_on_create roles all assigned — two roles have the flag; both are attached after first login');
    }

    public function test_no_assign_on_create_roles_exist_login_still_succeeds(): void
    {
        $this->markTestSkipped('No assign_on_create roles exist — login still succeeds, user is created with no roles, no error');
    }

    public function test_returning_user_does_not_get_roles_resynced(): void
    {
        $this->markTestSkipped('Returning user does not get roles re-synced — log in twice; role count on second login is unchanged (no duplicates)');
    }

    public function test_returning_user_who_lost_a_role_does_not_get_it_back(): void
    {
        $this->markTestSkipped('Returning user who lost a role does not get it back — user had a role revoked; next login does not re-attach it');
    }

    public function test_non_assign_on_create_roles_not_touched(): void
    {
        $this->markTestSkipped('Non-assign_on_create roles not touched — roles without the flag are never auto-attached');
    }

    public function test_user_already_holds_assign_on_create_role_no_duplicate_pivot_rows(): void
    {
        $this->markTestSkipped('User already holds one of the assign_on_create roles — idempotent, no duplicate pivot rows created');
    }
}
