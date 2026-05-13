<?php

namespace Tests\Feature\OAuth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Tests\TestCase;

class AutoAssignRolesOnCreateTest extends TestCase
{
    // On first OAuth login, all roles with assign_on_create=true are synced to the new user.

    protected function setUp(): void
    {
        parent::setUp();
        // Remove seeded assign_on_create roles so each test controls its own set
        Role::where('assign_on_create', true)->delete();
    }

    private function mockSocialiteUser(string $discordId, string $username = 'TestUser'): void
    {
        $fakeUser = (new SocialiteUser)->map([
            'id' => $discordId,
            'nickname' => $username,
            'name' => $username,
            'email' => 'test@example.com',
        ])->setToken('fake_token');

        $driverMock = Mockery::mock('Laravel\Socialite\Two\AbstractProvider');
        $statelessMock = Mockery::mock(ProviderInterface::class);
        $statelessMock->shouldReceive('user')->andReturn($fakeUser);
        $driverMock->shouldReceive('stateless')->andReturn($statelessMock);

        $redirectResponse = Mockery::mock(RedirectResponse::class);
        $redirectResponse->shouldReceive('getTargetUrl')->andReturn('https://discord.com/oauth2/authorize');
        $driverMock->shouldReceive('setScopes')->andReturn($driverMock);
        $driverMock->shouldReceive('scopes')->andReturn($driverMock);
        $driverMock->shouldReceive('withState')->andReturn($driverMock);
        $driverMock->shouldReceive('with')->andReturn($driverMock);
        $driverMock->shouldReceive('redirect')->andReturn($redirectResponse);

        Socialite::shouldReceive('driver')->with('discord')->andReturn($driverMock);
    }

    private function doCallback(string $discordId, string $username = 'TestUser'): void
    {
        $state = Str::random(40);
        session()->put('oauth.state', $state);
        $this->mockSocialiteUser($discordId, $username);
        $this->postJson('/web/oauth2/discord/callback', ['code' => 'code', 'state' => $state]);
    }

    public function test_new_user_gets_assign_on_create_roles(): void
    {
        $role = Role::factory()->assignOnCreate()->create();

        $this->doCallback('111000111000111000');

        $user = User::where('discord_id', '111000111000111000')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->roles->contains('id', $role->id));
    }

    public function test_multiple_assign_on_create_roles_all_assigned(): void
    {
        $role1 = Role::factory()->assignOnCreate()->create();
        $role2 = Role::factory()->assignOnCreate()->create();

        $this->doCallback('111000111000111001');

        $user = User::where('discord_id', '111000111000111001')->first();
        $this->assertTrue($user->roles->contains('id', $role1->id));
        $this->assertTrue($user->roles->contains('id', $role2->id));
    }

    public function test_no_assign_on_create_roles_exist_login_still_succeeds(): void
    {
        // No assign_on_create roles (setUp already deleted them)
        $this->doCallback('111000111000111002');

        $user = User::where('discord_id', '111000111000111002')->first();
        $this->assertNotNull($user);
        $this->assertCount(0, $user->roles);
    }

    public function test_returning_user_does_not_get_roles_resynced(): void
    {
        $role = Role::factory()->assignOnCreate()->create();

        $this->doCallback('111000111000111003');
        $countAfterFirst = User::where('discord_id', '111000111000111003')->first()->roles()->count();

        // Second login: no additional roles attached
        $this->doCallback('111000111000111003');
        $countAfterSecond = User::where('discord_id', '111000111000111003')->first()->roles()->count();

        $this->assertEquals($countAfterFirst, $countAfterSecond);
    }

    public function test_returning_user_who_lost_a_role_does_not_get_it_back(): void
    {
        $role = Role::factory()->assignOnCreate()->create();

        $this->doCallback('111000111000111004');

        $user = User::where('discord_id', '111000111000111004')->first();
        $user->roles()->detach($role->id);
        $this->assertFalse($user->roles()->where('id', $role->id)->exists());

        $this->doCallback('111000111000111004');

        $user->load('roles');
        $this->assertFalse($user->roles->contains('id', $role->id));
    }

    public function test_non_assign_on_create_roles_not_touched(): void
    {
        $normalRole = Role::factory()->create(['assign_on_create' => false]);

        $this->doCallback('111000111000111005');

        $user = User::where('discord_id', '111000111000111005')->first();
        $this->assertFalse($user->roles->contains('id', $normalRole->id));
    }

    public function test_user_already_holds_assign_on_create_role_no_duplicate_pivot_rows(): void
    {
        $role = Role::factory()->assignOnCreate()->create();

        // Pre-create user and attach the role manually
        $user = User::factory()->create(['discord_id' => '111000111000111006']);
        $user->roles()->attach($role->id);

        $this->doCallback('111000111000111006');

        $pivotCount = $user->roles()->where('roles.id', $role->id)->count();
        $this->assertEquals(1, $pivotCount);
    }
}
