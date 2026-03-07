<?php

namespace Tests\Unit\Jobs;

use App\Jobs\RefreshUserAvatarCache;
use App\Models\User;
use App\Services\NinjaKiwi\NinjaKiwiApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RefreshUserAvatarCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_refreshes_cache_for_user_with_oak()
    {
        Log::shouldReceive('warning')->never();
        NinjaKiwiApiClient::fake([
            'avatar_url' => 'https://example.com/avatar.png',
            'banner_url' => 'https://example.com/banner.png',
        ]);

        $user = User::factory()
            ->withOak('test_oak')
            ->create([
                'cached_avatar_url' => null,
                'cached_banner_url' => null,
                'ninjakiwi_cache_expire' => null,
            ]);

        $job = new RefreshUserAvatarCache($user->discord_id);
        $job->handle(app(\App\Services\UserService::class));

        $user->refresh();
        $this->assertEquals('https://example.com/avatar.png', $user->cached_avatar_url);
        $this->assertEquals('https://example.com/banner.png', $user->cached_banner_url);
        $this->assertNotNull($user->ninjakiwi_cache_expire);
    }

    public function test_job_skips_user_without_oak()
    {
        Log::shouldReceive('warning')->never();

        $user = User::factory()->create([
            'nk_oak' => null,
            'cached_avatar_url' => null,
            'cached_banner_url' => null,
            'ninjakiwi_cache_expire' => null,
        ]);

        $job = new RefreshUserAvatarCache($user->discord_id);
        $job->handle(app(\App\Services\UserService::class));

        $user->refresh();
        $this->assertNull($user->cached_avatar_url);
        $this->assertNull($user->cached_banner_url);
        $this->assertNull($user->ninjakiwi_cache_expire);
    }

    public function test_job_logs_warning_for_nonexistent_user()
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('User not found for avatar cache refresh', \Mockery::on(function ($context) {
                return isset($context['user_id']) && $context['user_id'] === 999;
            }));

        $job = new RefreshUserAvatarCache(999);
        $job->handle(app(\App\Services\UserService::class));
    }

    public function test_job_sets_random_expiry_between_5_and_15_minutes()
    {
        Carbon::setTestNow('2025-03-07 12:00:00');
        NinjaKiwiApiClient::fake([
            'avatar_url' => 'https://example.com/avatar.png',
            'banner_url' => 'https://example.com/banner.png',
        ]);

        $user = User::factory()
            ->withOak('test_oak')
            ->create([
                'cached_avatar_url' => null,
                'cached_banner_url' => null,
                'ninjakiwi_cache_expire' => null,
            ]);

        $job = new RefreshUserAvatarCache($user->discord_id);
        $job->handle(app(\App\Services\UserService::class));

        $user->refresh();

        $this->assertNotNull($user->ninjakiwi_cache_expire);

        $minExpiry = now()->addMinutes(5);
        $maxExpiry = now()->addMinutes(15);

        $this->assertTrue($user->ninjakiwi_cache_expire->gte($minExpiry));
        $this->assertTrue($user->ninjakiwi_cache_expire->lte($maxExpiry));
    }

    public function test_job_random_expiry_has_variance()
    {
        Carbon::setTestNow('2025-03-07 12:00:00');
        NinjaKiwiApiClient::fake([
            'avatar_url' => 'https://example.com/avatar.png',
            'banner_url' => 'https://example.com/banner.png',
        ]);

        $user = User::factory()
            ->withOak('test_oak')
            ->create([
                'cached_avatar_url' => null,
                'cached_banner_url' => null,
                'ninjakiwi_cache_expire' => null,
            ]);

        $expiries = [];

        // Run multiple times to test randomness
        for ($i = 0; $i < 20; $i++) {
            $user->update([
                'cached_avatar_url' => null,
                'cached_banner_url' => null,
                'ninjakiwi_cache_expire' => null,
            ]);

            $job = new RefreshUserAvatarCache($user->discord_id);
            $job->handle(app(\App\Services\UserService::class));

            $user->refresh();
            $expiries[] = $user->ninjakiwi_cache_expire->timestamp;
        }

        // All expiries should be between 5-15 minutes from now
        $minTimestamp = now()->addMinutes(5)->timestamp;
        $maxTimestamp = now()->addMinutes(15)->timestamp;

        foreach ($expiries as $expiry) {
            $this->assertGreaterThanOrEqual($minTimestamp, $expiry);
            $this->assertLessThanOrEqual($maxTimestamp, $expiry);
        }

        // Should have some variation (not all the same)
        $uniqueExpiries = array_unique($expiries);
        $this->assertGreaterThan(1, count($uniqueExpiries));
    }
}
