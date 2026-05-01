<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class DiscordUtilitiesController
{
    public function serverRoles(Request $request)
    {
        return response()->json(['message' => 'Not Implemented'], 501);
    }

    public function guildRoles(string $guildId)
    {
        $user = auth()->guard('discord')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (empty($user->formatsWithPermission('edit:achievement_roles'))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bot ' . config('services.discord.bot_token'),
        ])->get("https://discord.com/api/v10/guilds/{$guildId}/roles");

        return response()->json($response->json(), $response->status());
    }
}
