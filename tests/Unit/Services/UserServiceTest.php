<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\UserService;
use App\Services\NinjaKiwi\NinjaKiwiApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserService $userService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userService = app(UserService::class);
    }

    public function test_refresh_user_cache_does_not_dispatch_job_when_cache_is_valid()
    {
        NinjaKiwiApiClient::fake(['avatar_url' => 'test', 'banner_url' => 'test']);

        $user = User::factory()
            ->withOak('test_oak')
            ->create([
                'cached_avatar_url' => 'https://example.com/avatar.png',
                'cached_banner_url' => 'https://example.com/banner.png',
                'ninjakiwi_cache_expire' => now()->addMinutes(10),
            ]);

        $originalExpire = $user->ninjakiwi_cache_expire;

        $this->userService->refreshUserCache($user);

        // Cache should not be updated
        $user->refresh();
        $this->assertEquals($originalExpire->timestamp, $user->ninjakiwi_cache_expire->timestamp);
    }

    public function test_refresh_user_cache_skips_users_without_oak()
    {
        $user = User::factory()->create([
            'nk_oak' => null,
            'cached_avatar_url' => null,
            'cached_banner_url' => null,
            'ninjakiwi_cache_expire' => null,
        ]);

        $this->userService->refreshUserCache($user);

        // Cache should remain null
        $user->refresh();
        $this->assertNull($user->cached_avatar_url);
        $this->assertNull($user->cached_banner_url);
        $this->assertNull($user->ninjakiwi_cache_expire);
    }

    public function test_refresh_user_cache_with_force_refreshes_immediately()
    {
        NinjaKiwiApiClient::fake([
            'avatar_url' => 'https://example.com/new-avatar.png',
            'banner_url' => 'https://example.com/new-banner.png',
        ]);

        $user = User::factory()
            ->withOak('test_oak')
            ->create([
                'cached_avatar_url' => 'https://example.com/old-avatar.png',
                'cached_banner_url' => 'https://example.com/old-banner.png',
                'ninjakiwi_cache_expire' => now()->subMinutes(5),
            ]);

        $this->userService->refreshUserCache($user, force: true);

        // Cache should be updated immediately
        $user->refresh();
        $this->assertEquals('https://example.com/new-avatar.png', $user->cached_avatar_url);
        $this->assertEquals('https://example.com/new-banner.png', $user->cached_banner_url);
        $this->assertNotNull($user->ninjakiwi_cache_expire);
        $this->assertTrue($user->ninjakiwi_cache_expire->isFuture());
    }
}
