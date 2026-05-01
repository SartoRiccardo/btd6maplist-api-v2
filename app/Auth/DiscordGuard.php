<?php

namespace App\Auth;

use App\Models\Role;
use App\Models\User;
use App\Services\Discord\DiscordApiClient;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;

class DiscordGuard implements Guard
{
    protected ?User $user = null;
    protected Request $request;
    protected UserProvider $provider;

    public function __construct(UserProvider $provider, Request $request)
    {
        $this->provider = $provider;
        $this->request = $request;
    }

    public function check(): bool
    {
        return !is_null($this->user());
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function user(): ?User
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $token = $this->request->bearerToken();

        if (!$token) {
            return null;
        }

        try {
            $discordProfile = DiscordApiClient::getUserProfile($token);
            $user = User::firstOrCreate(
                ['discord_id' => $discordProfile['id']],
                [
                    'name' => $discordProfile['username'],
                    'has_seen_popup' => false,
                    'is_banned' => false,
                ]
            );

            if ($user->wasRecentlyCreated) {
                $assignOnCreateRoleIds = Role::where('assign_on_create', true)->pluck('id');
                if ($assignOnCreateRoleIds->isNotEmpty()) {
                    $user->roles()->syncWithoutDetaching($assignOnCreateRoleIds);
                }
            }

            return $this->user = $user;
        } catch (\RuntimeException $e) {
            return null;
        }
    }

    public function id(): int|string|null
    {
        return $this->user()?->id;
    }

    public function validate(array $credentials = []): bool
    {
        return false;
    }

    public function hasUser(): bool
    {
        return !is_null($this->user);
    }

    public function setUser(Authenticatable $user): void
    {
        $this->user = $user;
    }
}
