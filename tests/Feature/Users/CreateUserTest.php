<?php

namespace Tests\Feature\Users;

use Tests\TestCase;

class CreateUserTest extends TestCase
{
    // POST /users — Create User
    // Creates a user with a given discord_id and name. Requires global create:user permission. Auto-assigns assign_on_create roles.

    public function test_creates_user_with_valid_discord_id_and_name(): void
    {
        $this->markTestSkipped('Creates user with valid discord_id and name — 201, user exists in DB');
    }

    public function test_response_includes_newly_assigned_assign_on_create_roles(): void
    {
        $this->markTestSkipped('Response includes roles — newly assigned assign_on_create roles present in response');
    }

    public function test_assign_on_create_roles_auto_assigned_on_creation(): void
    {
        $this->markTestSkipped('assign_on_create roles auto-assigned on creation');
    }

    public function test_unauthenticated_returns_401(): void
    {
        $this->markTestSkipped('Unauthenticated returns 401');
    }

    public function test_no_create_user_permission_returns_403(): void
    {
        $this->markTestSkipped('No create:user permission returns 403');
    }

    public function test_missing_discord_id_returns_422(): void
    {
        $this->markTestSkipped('Missing discord_id returns 422');
    }

    public function test_missing_name_returns_422(): void
    {
        $this->markTestSkipped('Missing name returns 422');
    }

    public function test_non_numeric_discord_id_returns_422(): void
    {
        $this->markTestSkipped('Non-numeric discord_id returns 422 (e.g. "abc")');
    }

    public function test_discord_id_already_exists_returns_422(): void
    {
        $this->markTestSkipped('discord_id already exists returns 422');
    }

    public function test_name_already_taken_exact_case_returns_422(): void
    {
        $this->markTestSkipped('Name already taken (exact case) returns 422');
    }

    public function test_name_already_taken_different_case_returns_422(): void
    {
        $this->markTestSkipped('Name already taken (different case) returns 422 — uniqueness is case-insensitive');
    }

    public function test_name_over_50_chars_returns_422(): void
    {
        $this->markTestSkipped('Name over 50 chars returns 422');
    }

    public function test_no_assign_on_create_roles_exist_user_still_created_successfully(): void
    {
        $this->markTestSkipped('No assign_on_create roles exist — user still created successfully');
    }
}
