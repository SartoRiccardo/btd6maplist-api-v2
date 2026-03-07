<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\UpdateUserRequest;
use App\Models\AchievementRole;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController
{
    /**
     * @OA\Get(
     *     path="/users/{id}",
     *     summary="Get user by ID",
     *     description="Returns a user's profile data including their platform roles. If the user has a Ninja Kiwi OAK set and 'flair' is in the include parameter, avatar and banner URLs are fetched from the Ninja Kiwi API. If 'medals' is included, medal statistics are calculated for the specified timestamp.",
     *     tags={"Users"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The user's Discord ID",
     *         @OA\Schema(type="string", example="123456789012345678")
     *     ),
     *     @OA\Parameter(
     *         name="include",
     *         in="query",
     *         required=false,
     *         description="Comma-separated list of additional data to include. Use 'flair' to include avatar_url and banner_url, 'medals' to include medal statistics.",
     *         @OA\Schema(type="string", example="flair,medals")
     *     ),
     *     @OA\Parameter(
     *         name="timestamp",
     *         in="query",
     *         required=false,
     *         description="Unix timestamp to calculate medals at. Defaults to current time.",
     *         @OA\Schema(type="integer", example=1704067200)
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="User profile data",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(ref="#/components/schemas/User")
     *         )
     *     ),
     *     @OA\Response(response="404", description="User not found")
     * )
     */
    public function show(string $id, Request $request)
    {
        $includes = array_filter(explode(',', $request->query('include', '')));
        $includeFlair = in_array('flair', $includes, true);
        $includeMedals = in_array('medals', $includes, true);
        $includeAchRoles = in_array('achievement_roles', $includes, true);

        // Default timestamp to now, similar to CompletionController
        $timestamp = $request->query('timestamp', Carbon::now()->unix());
        if (!is_numeric($timestamp)) {
            return response()->json(['message' => 'Invalid timestamp'], 422);
        }
        $timestamp = (int) $timestamp;

        $user = User::with('roles')->find($id);
        if (!$user) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        // Append flair if requested
        if ($includeFlair) {
            $user->appendFlair();
        }

        // Trigger background cache refresh if needed
        $userService = app(UserService::class);
        $userService->refreshUserCache($user);

        $response = $user->toArray();

        // Include medal statistics if 'medals' is in includes
        if ($includeMedals) {
            $carbonTimestamp = Carbon::createFromTimestamp($timestamp);
            $response['medals'] = $user->medals($carbonTimestamp);
        }

        // Include achievement roles
        if ($includeAchRoles) {
            $response['achievement_roles'] = AchievementRole::forUser($user->discord_id)->toArray();
        }

        return response()->json($response);
    }

    /**
     * @OA\Put(
     *     path="/users/{id}",
     *     summary="Update user profile",
     *     description="Updates a user's profile information. The {id} parameter can be the literal string '@me' to update the authenticated user's profile, or a numeric Discord ID matching the authenticated user's ID. Currently, updating other users returns 501 Not Implemented. Requires the edit:self permission.",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="User ID or '@me' for the authenticated user",
     *         @OA\Schema(type="string", example="@me")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(ref="#/components/schemas/UpdateUserRequest")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User updated successfully",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(ref="#/components/schemas/User")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - No valid Discord token provided"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have edit:self permission"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=501,
     *         description="Not Implemented - Updating other users is not yet supported"
     *     )
     * )
     */
    public function update(UpdateUserRequest $request, $id)
    {
        $user = auth()->guard('discord')->user();

        // Permission check
        if (!$user->hasPermission('edit:self', null)) {
            return response()->json([
                'message' => 'Forbidden - You do not have permission to edit user profiles.',
            ], 403);
        }

        // Resolve @me alias
        if ($id === '@me') {
            $id = $user->discord_id;
        }

        // Temporary constraint: Only allow updating self
        if ($id !== $user->discord_id) {
            return response()->json(['message' => 'Not Implemented'], 501);
        }

        // Find target user (should be self at this point)
        $targetUser = User::find($id);
        if (!$targetUser) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        // Case-insensitive name uniqueness check (after @me resolution)
        $validatedData = $request->validated();
        if (isset($validatedData['name'])) {
            $existingUser = User::whereRaw('LOWER(name) = LOWER(?)', [$validatedData['name']])
                ->where('discord_id', '!=', $id)
                ->first();

            if ($existingUser) {
                return response()->json([
                    'errors' => [
                        'name' => ['The name has already been taken.'],
                    ],
                ], 422);
            }
        }

        // Update user with validated data
        $targetUser->update($validatedData);

        // Refresh cache if nk_oak was changed and cache is stale
        if (isset($validatedData['nk_oak'])) {
            $userService = app(UserService::class);
            $userService->refreshUserCache($targetUser);
        }

        return response()->json($targetUser);
    }

    /**
     * @OA\Put(
     *     path="/users/{id}/ban",
     *     summary="Ban a user",
     *     description="Bans a user by setting is_banned to true and removing all roles marked as assign_on_create. Requires the ban:user permission at the GLOBAL level (format-independent). This operation is idempotent - calling it multiple times on the same user will not cause errors.",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The Discord ID of the user to ban",
     *         @OA\Schema(type="string", example="123456789012345678")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="User successfully banned"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - No valid Discord token provided"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have global ban:user permission"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     )
     * )
     */
    public function banUser(Request $request, $id)
    {
        $user = auth()->guard('discord')->user();

        // Permission check: Must have ban:user at GLOBAL level
        if (!$user->hasPermission('ban:user', null)) {
            return response()->json([
                'message' => 'Forbidden - You do not have permission to ban users.',
            ], 403);
        }

        // Find target user
        $targetUser = User::find($id);
        if (!$targetUser) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return DB::transaction(function () use ($targetUser) {
            // Idempotent: Only set is_banned if not already banned
            if (!$targetUser->is_banned) {
                $targetUser->is_banned = true;
                $targetUser->save();
            }

            // Remove all assign_on_create roles from user
            $assignOnCreateRoleIds = Role::where('assign_on_create', true)
                ->pluck('id');

            if ($assignOnCreateRoleIds->isNotEmpty()) {
                $targetUser->roles()->detach($assignOnCreateRoleIds);
            }

            return response()->noContent();
        });
    }

    /**
     * @OA\Put(
     *     path="/users/{id}/unban",
     *     summary="Unban a user",
     *     description="Unbans a user by setting is_banned to false and restoring all roles marked as assign_on_create. Requires the ban:user permission at the GLOBAL level (format-independent). This operation is idempotent - calling it multiple times will not duplicate roles.",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The Discord ID of the user to unban",
     *         @OA\Schema(type="string", example="123456789012345678")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="User successfully unbanned"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - No valid Discord token provided"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have global ban:user permission"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     )
     * )
     */
    public function unbanUser(Request $request, $id)
    {
        $user = auth()->guard('discord')->user();

        // Permission check: Must have ban:user at GLOBAL level
        if (!$user->hasPermission('ban:user', null)) {
            return response()->json([
                'message' => 'Forbidden - You do not have permission to unban users.',
            ], 403);
        }

        // Find target user
        $targetUser = User::find($id);
        if (!$targetUser) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return DB::transaction(function () use ($targetUser) {
            // Idempotent: Only set is_banned if currently banned
            if ($targetUser->is_banned) {
                $targetUser->is_banned = false;
                $targetUser->save();
            }

            // Restore all assign_on_create roles
            $assignOnCreateRoleIds = Role::where('assign_on_create', true)
                ->pluck('id');

            if ($assignOnCreateRoleIds->isNotEmpty()) {
                // syncWithoutDetaching: adds roles without removing existing ones
                $targetUser->roles()->syncWithoutDetaching($assignOnCreateRoleIds);
            }

            return response()->noContent();
        });
    }

    public function readRules(Request $request)
    {
        $user = auth()->guard('discord')->user();
        $user->has_seen_popup = true;
        $user->save();

        return response()->noContent();
    }
}
