<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Str;

class OAuthController extends Controller
{
    /**
     * Get Discord OAuth2 authorization URL.
     *
     * @OA\Post(
     *     path="/web/oauth2/discord/login",
     *     summary="Get Discord OAuth2 authorization URL",
     *     tags={"Web - OAuth2"},
     *     @OA\Response(
     *         response=200,
     *         description="Returns the authorization URL",
     *         @OA\JsonContent(
     *             @OA\Property(property="url", type="string", example="https://discord.com/oauth2/authorize?...")
     *         )
     *     )
     * )
     */
    public function discordRedirect(Request $request): JsonResponse
    {
        $state = Str::random(40);
        session()->put('oauth.state', $state);

        $url = Socialite::driver('discord')
            ->setScopes(['identify'])
            ->with(['state' => $state])
            ->redirect()
            ->getTargetUrl();

        return response()->json(['url' => $url]);
    }

    /**
     * Handle Discord OAuth2 callback.
     *
     * @OA\Post(
     *     path="/web/oauth2/discord/callback",
     *     summary="Handle Discord OAuth2 callback",
     *     tags={"Web - OAuth2"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="string"),
     *             @OA\Property(property="state", type="string"),
     *             @OA\Property(property="error", type="string", example="access_denied")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OAuth successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="token", type="string"),
     *             @OA\Property(property="user", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="OAuth error"),
     *     @OA\Response(response=401, description="Invalid state")
     * )
     */
    public function discordCallback(Request $request): JsonResponse
    {
        if ($request->has('error')) {
            return response()->json([
                'error' => $request->input('error'),
                'description' => $request->input('error_description', 'OAuth authorization failed'),
            ], 400);
        }

        $code = $request->input('code');
        $state = $request->input('state');

        if (!$code || !$state) {
            return response()->json([
                'error' => 'missing_parameters',
                'message' => 'code and state are required',
            ], 400);
        }

        $storedState = session()->pull('oauth.state');

        if (!$storedState || $state !== $storedState) {
            return response()->json([
                'error' => 'invalid_state',
                'message' => 'Invalid state parameter',
            ], 401);
        }

        try {
            $socialiteUser = Socialite::driver('discord')->stateless()->user();

            $user = User::firstOrCreate(
                ['discord_id' => $socialiteUser->id],
                [
                    'name' => $socialiteUser->name,
                    'has_seen_popup' => false,
                    'is_banned' => false,
                ]
            );

            $token = $socialiteUser->token;
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'oauth_failed',
                'message' => 'Failed to exchange code for access token',
            ], 400);
        }

        // TODO this is bad it should align with like. get /users/@me or something.
        return response()->json([
            'token' => $token,
            'user' => [
                'discord_id' => $user->discord_id,
                'name' => $user->name,
            ],
        ]);
    }
}
