<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Database\UniqueConstraintViolationException;
use Symfony\Component\HttpFoundation\Response;

class BotUserResolver
{
    public function handle(Request $request, Closure $next): Response
    {
        $botUser = $request->input('_user');

        if (
            !is_array($botUser) ||
            empty($botUser['discord_id']) ||
            empty($botUser['name'])
        ) {
            return response()->json(['error' => 'Missing or invalid _user payload'], 422);
        }

        try {
            $user = User::firstOrCreate(
                ['discord_id' => $botUser['discord_id']],
                [
                    'name' => $botUser['name'],
                    'has_seen_popup' => false,
                    'is_banned' => false,
                ]
            );
        } catch (UniqueConstraintViolationException) {
            $user = User::firstOrCreate(
                ['discord_id' => $botUser['discord_id']],
                [
                    'name' => $botUser['name'] . Str::random(5),
                    'has_seen_popup' => false,
                    'is_banned' => false,
                ]
            );
        }

        if ($user->wasRecentlyCreated) {
            $assignOnCreateRoleIds = Role::where('assign_on_create', true)->pluck('id');
            if ($assignOnCreateRoleIds->isNotEmpty()) {
                $user->roles()->syncWithoutDetaching($assignOnCreateRoleIds);
            }
        }

        auth()->guard('discord')->setUser($user);

        return $next($request);
    }
}
