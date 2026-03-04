<?php

namespace App\Http\Controllers;

use App\Http\Requests\AchievementRole\IndexAchievementRoleRequest;
use App\Http\Requests\AchievementRole\StoreAchievementRoleRequest;
use App\Http\Requests\AchievementRole\UpdateAchievementRoleRequest;
use App\Models\AchievementRole;
use Illuminate\Support\Facades\DB;

class AchievementRoleController extends Controller
{
    /**
     * Get paginated list of achievement roles.
     *
     * @OA\Get(
     *     path="/roles/achievement",
     *     summary="Get achievement roles",
     *     description="Retrieves paginated achievement roles with optional filters. Public access.",
     *     tags={"Achievement Roles"},
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", minimum=1, example=1)),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=100, example=15)),
     *     @OA\Parameter(name="format_id", in="query", required=false, @OA\Schema(type="integer", description="Filter by leaderboard format ID")),
     *     @OA\Parameter(name="type", in="query", required=false, @OA\Schema(type="string", description="Filter by leaderboard type")),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated achievement roles",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AchievementRoleWithDiscordRoles")),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(IndexAchievementRoleRequest $request)
    {
        $validated = $request->validated();

        $page = $validated['page'] ?? 1;
        $perPage = $validated['per_page'] ?? 15;

        $query = AchievementRole::withFilters(
            $validated['format_id'] ?? null,
            $validated['type'] ?? null
        )->with('discordRoles');

        $roles = $query->orderBy('id')->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $roles->items(),
            'meta' => [
                'current_page' => $roles->currentPage(),
                'last_page' => $roles->lastPage(),
                'per_page' => $roles->perPage(),
                'total' => $roles->total(),
            ],
        ]);
    }

    /**
     * Get a specific achievement role by ID.
     *
     * @OA\Get(
     *     path="/roles/achievement/{id}",
     *     summary="Get achievement role by ID",
     *     description="Returns achievement role details with associated Discord roles. Public access.",
     *     tags={"Achievement Roles"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", description="Achievement role ID")),
     *     @OA\Response(
     *         response=200,
     *         description="Achievement role data",
     *         @OA\JsonContent(ref="#/components/schemas/AchievementRoleWithDiscordRoles")
     *     ),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show($id)
    {
        $role = AchievementRole::with('discordRoles')->find($id);

        if (!$role) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return response()->json($role);
    }

    /**
     * Create a new achievement role.
     *
     * @OA\Post(
     *     path="/roles/achievement",
     *     summary="Create achievement role",
     *     description="Creates a new achievement role with associated Discord roles. Requires edit:achievement_roles permission for the format.",
     *     tags={"Achievement Roles"},
     *     security={{"discordAuth":{}}},
     *     @OA\RequestBody(@OA\JsonContent(ref="#/components/schemas/StoreAchievementRoleRequest")),
     *     @OA\Response(
     *         response=201,
     *         description="Achievement role created",
     *         @OA\JsonContent(ref="#/components/schemas/AchievementRoleWithDiscordRoles")
     *     ),
     *     @OA\Response(response=401, description="Authentication required"),
     *     @OA\Response(response=403, description="Missing edit:achievement_roles permission"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreAchievementRoleRequest $request)
    {
        $user = auth()->guard('discord')->user();
        $lbFormat = $request->input('lb_format');

        if (!$user->hasPermission('edit:achievement_roles', $lbFormat)) {
            return response()->json(['message' => 'Forbidden - Missing edit:achievement_roles permission for this format.'], 403);
        }

        $role = DB::transaction(function () use ($request) {
            $role = AchievementRole::create($request->except('discord_roles'));

            if (!empty($request->input('discord_roles'))) {
                $role->discordRoles()->createMany($request->input('discord_roles'));
            }

            return $role;
        });

        return response()->json($role->load('discordRoles'), 201);
    }

    /**
     * Update an existing achievement role.
     *
     * @OA\Put(
     *     path="/roles/achievement/{id}",
     *     summary="Update achievement role",
     *     description="Updates an achievement role. All fields required. Replaces discord_roles completely. Requires edit:achievement_roles permission for the format.",
     *     tags={"Achievement Roles"},
     *     security={{"discordAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", description="Achievement role ID")),
     *     @OA\RequestBody(@OA\JsonContent(ref="#/components/schemas/UpdateAchievementRoleRequest")),
     *     @OA\Response(
     *         response=200,
     *         description="Achievement role updated",
     *         @OA\JsonContent(ref="#/components/schemas/AchievementRoleWithDiscordRoles")
     *     ),
     *     @OA\Response(response=401, description="Authentication required"),
     *     @OA\Response(response=403, description="Missing edit:achievement_roles permission"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update($id, UpdateAchievementRoleRequest $request)
    {
        $user = auth()->guard('discord')->user();
        $role = AchievementRole::find($id);

        if (!$role) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        if (!$user->hasPermission('edit:achievement_roles', $role->lb_format)) {
            return response()->json(['message' => 'Forbidden - Missing edit:achievement_roles permission for this format.'], 403);
        }

        $role = DB::transaction(function () use ($request, $role) {
            $role->update($request->except('discord_roles'));

            // Replace discord_roles completely
            $role->discordRoles()->delete();
            if (!empty($request->input('discord_roles'))) {
                $role->discordRoles()->createMany($request->input('discord_roles'));
            }

            return $role;
        });

        return response()->json($role->load('discordRoles'), 200);
    }

    /**
     * Delete an achievement role.
     *
     * @OA\Delete(
     *     path="/roles/achievement/{id}",
     *     summary="Delete achievement role",
     *     description="Deletes an achievement role. Associated Discord roles are cascade deleted. Requires edit:achievement_roles permission for the format.",
     *     tags={"Achievement Roles"},
     *     security={{"discordAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", description="Achievement role ID")),
     *     @OA\Response(response=204, description="Achievement role deleted"),
     *     @OA\Response(response=401, description="Authentication required"),
     *     @OA\Response(response=403, description="Missing edit:achievement_roles permission"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy($id)
    {
        $user = auth()->guard('discord')->user();
        $role = AchievementRole::find($id);

        if (!$role) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        if (!$user->hasPermission('edit:achievement_roles', $role->lb_format)) {
            return response()->json(['message' => 'Forbidden - Missing edit:achievement_roles permission for this format.'], 403);
        }

        $role->delete();

        return response()->noContent();
    }
}
