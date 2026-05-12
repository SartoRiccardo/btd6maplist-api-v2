<?php

namespace Tests\Feature\Roles\Platform;

use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Tests\Helpers\RoleTestHelper;
use Tests\TestCase;

class IndexTest extends TestCase
{
    #[Group('get')]
    #[Group('roles')]
    #[Group('platform')]
    public function test_returns_platform_roles_with_can_grant(): void
    {
        Role::query()->delete();
        DB::table('role_grants')->truncate();

        $grantableRole1 = Role::factory()->create();
        $grantableRole2 = Role::factory()->create();

        $internalRole1 = Role::factory()->internal()->canGrant([$grantableRole1->id, $grantableRole2->id])->create();
        $internalRole2 = Role::factory()->internal()->canGrant([$grantableRole1->id])->create();
        $internalRole3 = Role::factory()->internal()->create();
        $nonInternalRole = Role::factory()->create();

        $internalRole1->load('canGrant');
        $internalRole2->load('canGrant');
        $internalRole3->load('canGrant');
        $grantableRole1->load('canGrant');
        $grantableRole2->load('canGrant');
        $nonInternalRole->load('canGrant');

        $actual = $this->getJson('/api/roles/platform')
            ->assertStatus(200)
            ->json();

        $allRoles = collect([$grantableRole1, $grantableRole2, $internalRole1, $internalRole2, $internalRole3, $nonInternalRole])
            ->sortBy('id')->values();
        $expected = RoleTestHelper::expectedPlatformRoleList($allRoles);

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('roles')]
    #[Group('platform')]
    public function test_returns_empty_array_when_no_platform_roles_exist(): void
    {
        Role::query()->delete();
        DB::table('role_grants')->truncate();

        $actual = $this->getJson('/api/roles/platform')
            ->assertStatus(200)
            ->json();

        $expected = [
            'data' => [],
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 100,
                'total' => 0,
            ],
        ];

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('roles')]
    #[Group('platform')]
    #[Group('pagination')]
    public function test_returns_platform_roles_with_custom_pagination(): void
    {
        Role::query()->delete();
        DB::table('role_grants')->truncate();

        $roles = Role::factory()->count(5)->internal()->create();

        $page = 2;
        $perPage = 2;

        $pageRoles = $roles->sortBy('id')->forPage($page, $perPage)->values();

        $actual = $this->getJson("/api/roles/platform?page={$page}&per_page={$perPage}")
            ->assertStatus(200)
            ->json();

        $expected = RoleTestHelper::expectedPlatformRoleList($pageRoles, [
            'current_page' => $page,
            'last_page' => (int) ceil(5 / $perPage),
            'per_page' => $perPage,
            'total' => 5,
        ]);

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('roles')]
    #[Group('platform')]
    #[Group('validation')]
    public function test_validates_per_page_maximum(): void
    {
        $this->getJson('/api/roles/platform?per_page=500')
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('per_page');
    }
}
