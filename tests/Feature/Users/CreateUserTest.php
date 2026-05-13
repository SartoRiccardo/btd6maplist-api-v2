<?php

namespace Tests\Feature\Users;

use App\Models\Role;
use App\Models\User;
use Tests\TestCase;
use Tests\Traits\TestsDiscordAuthMiddleware;

class CreateUserTest extends TestCase
{
    use TestsDiscordAuthMiddleware;

    // POST /api/users — Creates a new user. Requires global create:user permission.

    protected function endpoint(): string { return '/api/users'; }
    protected function method(): string { return 'POST'; }
    protected function requestData(): array { return ['discord_id' => '111111111111111111', 'name' => 'TraitTestUser']; }

    protected function setUp(): void
    {
        parent::setUp();
        // Remove seeded assign_on_create roles for isolation
        Role::where('assign_on_create', true)->delete();
    }

    private function actorWithPermission(): User
    {
        return $this->createUserWithPermissions([null => ['create:user']]);
    }

    public function test_creates_user_with_valid_discord_id_and_name_returns_201(): void
    {
        $actor = $this->actorWithPermission();

        $this->actingAs($actor, 'discord')
            ->postJson('/api/users', ['discord_id' => '222333444555666777', 'name' => 'NewUser'])
            ->assertStatus(201);

        $this->assertDatabaseHas('users', ['discord_id' => '222333444555666777', 'name' => 'NewUser']);
    }

    public function test_response_includes_roles_newly_assigned_assign_on_create_roles_present(): void
    {
        $actor = $this->actorWithPermission();
        $role = Role::factory()->assignOnCreate()->create();

        $response = $this->actingAs($actor, 'discord')
            ->postJson('/api/users', ['discord_id' => '222333444555666778', 'name' => 'NewUser2'])
            ->assertStatus(201)
            ->json();

        $roleIds = collect($response['roles'])->pluck('id')->toArray();
        $this->assertContains($role->id, $roleIds);
    }

    public function test_assign_on_create_roles_auto_assigned_on_creation(): void
    {
        $actor = $this->actorWithPermission();
        $role = Role::factory()->assignOnCreate()->create();

        $this->actingAs($actor, 'discord')
            ->postJson('/api/users', ['discord_id' => '222333444555666779', 'name' => 'NewUser3'])
            ->assertStatus(201);

        $newUser = User::where('discord_id', '222333444555666779')->first();
        $this->assertTrue($newUser->roles->contains('id', $role->id));
    }

    public function test_no_create_user_permission_returns_403(): void
    {
        $actor = $this->createUserWithPermissions([]);

        $this->actingAs($actor, 'discord')
            ->postJson('/api/users', ['discord_id' => '222333444555666781', 'name' => 'X'])
            ->assertStatus(403);
    }

    public function test_missing_discord_id_returns_422(): void
    {
        $actor = $this->actorWithPermission();

        $this->actingAs($actor, 'discord')
            ->postJson('/api/users', ['name' => 'NoId'])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('discord_id');
    }

    public function test_missing_name_returns_422(): void
    {
        $actor = $this->actorWithPermission();

        $this->actingAs($actor, 'discord')
            ->postJson('/api/users', ['discord_id' => '222333444555666782'])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('name');
    }

    public function test_non_numeric_discord_id_returns_422(): void
    {
        $actor = $this->actorWithPermission();

        $this->actingAs($actor, 'discord')
            ->postJson('/api/users', ['discord_id' => 'notanumber', 'name' => 'X'])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('discord_id');
    }

    public function test_discord_id_already_exists_returns_422(): void
    {
        $actor = $this->actorWithPermission();
        User::factory()->create(['discord_id' => '222333444555666783']);

        $this->actingAs($actor, 'discord')
            ->postJson('/api/users', ['discord_id' => '222333444555666783', 'name' => 'Duplicate'])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('discord_id');
    }

    public function test_name_already_taken_exact_case_returns_422(): void
    {
        $actor = $this->actorWithPermission();
        User::factory()->create(['name' => 'ExistingUser']);

        $this->actingAs($actor, 'discord')
            ->postJson('/api/users', ['discord_id' => '222333444555666784', 'name' => 'ExistingUser'])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('name');
    }

    public function test_name_already_taken_different_case_returns_422(): void
    {
        $actor = $this->actorWithPermission();
        User::factory()->create(['name' => 'ExistingUser']);

        $this->actingAs($actor, 'discord')
            ->postJson('/api/users', ['discord_id' => '222333444555666785', 'name' => 'existinguser'])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('name');
    }

    public function test_name_over_50_chars_returns_422(): void
    {
        $actor = $this->actorWithPermission();

        $this->actingAs($actor, 'discord')
            ->postJson('/api/users', ['discord_id' => '222333444555666786', 'name' => str_repeat('a', 51)])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('name');
    }

    public function test_no_assign_on_create_roles_exist_user_still_created(): void
    {
        $actor = $this->actorWithPermission();

        $this->actingAs($actor, 'discord')
            ->postJson('/api/users', ['discord_id' => '222333444555666787', 'name' => 'NoRoles'])
            ->assertStatus(201);

        $this->assertDatabaseHas('users', ['discord_id' => '222333444555666787']);
    }
}
