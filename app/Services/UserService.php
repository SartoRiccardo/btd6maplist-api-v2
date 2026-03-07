<?php

namespace App\Services;

use App\Jobs\RefreshUserAvatarCache;
use App\Models\User;
use App\Services\NinjaKiwi\NinjaKiwiApiClient;

class UserService
{
    /**
     * Get user decoration (avatar and banner URLs) from Ninja Kiwi.
     *
     * @param string $nkOak User's Ninja Kiwi OAK
     * @return array|null Array with 'avatar_url' and 'banner_url', or null if not found
     */
    public function getUserDeco(string $nkOak): ?array
    {
        return NinjaKiwiApiClient::getBtd6UserDeco($nkOak);
    }

    /**
     * Synchronously refresh a user's avatar cache from Ninja Kiwi API.
     * Sets cache expiry to random time between 5-15 minutes from now.
     */
    public function refreshAvatarCache(User $user): void
    {
        if (!$user->nk_oak) {
            return;
        }

        $deco = NinjaKiwiApiClient::getBtd6UserDeco($user->nk_oak);

        $user->cached_avatar_url = $deco['avatar_url'];
        $user->cached_banner_url = $deco['banner_url'];

        // Random expiry between 5-15 minutes (300-900 seconds)
        $expirySeconds = rand(5 * 60, 15 * 60);
        $user->ninjakiwi_cache_expire = now()->addSeconds($expirySeconds);

        $user->save();
    }

    /**
     * Check if user needs cache refresh and dispatch job if needed.
     * This is the ONLY method that should dispatch refresh jobs.
     *
     * @param User $user The user to check/refresh
     * @param bool $force If true, immediately refreshes synchronously (for PUT /users)
     */
    public function refreshUserCache(User $user, bool $force = false): void
    {
        // No OAK means nothing to cache
        if (!$user->nk_oak) {
            return;
        }

        // Force = synchronous refresh (for PUT /users endpoint)
        if ($force) {
            $this->refreshAvatarCache($user);
            return;
        }

        // Check if cache is expired or empty
        $needsRefresh = !$user->ninjakiwi_cache_expire ||
                        !$user->ninjakiwi_cache_expire->isFuture();

        if ($needsRefresh) {
            // Dispatch background job to refresh
            RefreshUserAvatarCache::dispatch($user->discord_id);
        }
    }

    /**
     * Validate a Ninja Kiwi OAK by checking if it returns valid avatar/banner data.
     * Used for validation in UpdateUserRequest.
     *
     * @param string $nkOak The OAK to validate
     * @return bool True if OAK is valid (returns at least one URL)
     */
    public function validateOak(string $nkOak): bool
    {
        $deco = NinjaKiwiApiClient::getBtd6UserDeco($nkOak);

        return $deco['avatar_url'] !== null || $deco['banner_url'] !== null;
    }
}
