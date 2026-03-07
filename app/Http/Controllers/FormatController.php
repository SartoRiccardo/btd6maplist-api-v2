<?php

namespace App\Http\Controllers;

use App\Http\Requests\Format\IndexFormatRequest;
use App\Http\Requests\Format\LeaderboardRequest;
use App\Http\Requests\Format\UpdateFormatRequest;
use App\Models\Format;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FormatController
{
    /**
     * Get a paginated list of formats.
     *
     * @OA\Get(
     *     path="/formats",
     *     summary="Get list of formats",
     *     description="Retrieves a paginated list of all available formats. Webhook URLs and emoji are excluded from the response for security.",
     *     tags={"Formats"},
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexFormatRequest/properties/page")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexFormatRequest/properties/per_page")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Format")),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(IndexFormatRequest $request)
    {
        $validated = $request->validated();

        $page = $validated['page'];
        $perPage = $validated['per_page'];

        $formats = Format::query()
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $formats->items(),
            'meta' => [
                'current_page' => $formats->currentPage(),
                'last_page' => $formats->lastPage(),
                'per_page' => $formats->perPage(),
                'total' => $formats->total(),
            ],
        ]);
    }

    /**
     * Get a format by ID.
     *
     * @OA\Get(
     *     path="/formats/{id}",
     *     summary="Get format by ID",
     *     description="Returns format details. Sensitive fields (webhooks, emoji) are hidden by default. Use ?include=webhooks to reveal them (requires edit:config permission).",
     *     tags={"Formats"},
     *     @OA\Parameter(name="id", in="path", required=true, description="Format ID", @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="include", in="query", required=false, @OA\Schema(type="string", enum={"webhooks"})),
     *     @OA\Response(response=200, description="Format data", @OA\JsonContent(ref="#/components/schemas/Format")),
     *     @OA\Response(response=403, description="Missing edit:config permission for webhooks"),
     *     @OA\Response(response=404, description="Format not found"),
     *     @OA\Response(response=401, description="Authentication required for ?include=webhooks")
     * )
     */
    public function show($id, Request $request)
    {
        $validated = $request->validate([
            'include' => ['nullable', 'string', 'in:webhooks'],
        ]);

        $format = Format::find($id);
        if (!$format) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $include = $validated['include'] ?? null;
        if ($include === 'webhooks') {
            $user = auth()->guard('discord')->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized - Authentication required.'], 401);
            }
            if (!$user->hasPermission('edit:config', $format->id)) {
                return response()->json(['message' => 'Forbidden - Missing edit:config permission for this format.'], 403);
            }
            return response()->json($format->toFullArray());
        }

        return response()->json($format->toArray());
    }

    /**
     * Update a format.
     *
     * @OA\Put(
     *     path="/formats/{id}",
     *     summary="Update format",
     *     description="Updates format configuration. Requires edit:config permission for the format.",
     *     tags={"Formats"},
     *     security={{"discordAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Format ID", @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(@OA\JsonContent(ref="#/components/schemas/UpdateFormatRequest")),
     *     @OA\Response(response=204, description="Format updated successfully"),
     *     @OA\Response(response=401, description="Authentication required"),
     *     @OA\Response(response=403, description="Missing edit:config permission"),
     *     @OA\Response(response=404, description="Format not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update($id, UpdateFormatRequest $request)
    {
        $user = auth()->guard('discord')->user();
        $format = Format::find($id);
        if (!$format) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        if (!$user->hasPermission('edit:config', $format->id)) {
            return response()->json(['message' => 'Forbidden - Missing edit:config permission for this format.'], 403);
        }

        $format->update($request->validated());

        return response()->noContent();
    }

    /**
     * Get leaderboard for a format.
     *
     * @OA\Get(
     *     path="/formats/{id}/leaderboard",
     *     summary="Get leaderboard for a format",
     *     description="Retrieves a paginated leaderboard for the specified format. Use 'user.flair' in include parameter to add avatar_url and banner_url to each user.",
     *     tags={"Formats"},
     *     @OA\Parameter(name="id", in="path", required=true, description="Format ID", @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="value", in="query", required=false, @OA\Schema(ref="#/components/schemas/LeaderboardRequest/properties/value")),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(ref="#/components/schemas/LeaderboardRequest/properties/page")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(ref="#/components/schemas/LeaderboardRequest/properties/per_page")),
     *     @OA\Parameter(name="include", in="query", required=false, @OA\Schema(ref="#/components/schemas/LeaderboardRequest/properties/include")),
     *     @OA\Response(
     *         response=200,
     *         description="Leaderboard entries",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="score", type="number", format="float", example=1234.5),
     *                 @OA\Property(property="placement", type="integer", example=1),
     *                 @OA\Property(property="user", ref="#/components/schemas/User")
     *             )),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Format not found or no leaderboard available"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function leaderboard(string $id, LeaderboardRequest $request)
    {
        $leaderboardName = $request->getLeaderboardName((int) $id);
        if (!$leaderboardName) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $query = $request->buildLeaderboardQuery((int) $id);
        $validated = $request->validated();
        $perPage = $validated['per_page'];
        $includes = $request->includes();
        $includeUserFlair = in_array('user.flair', $includes, true);

        $results = DB::table(DB::raw("({$query}) lb"))
            ->select('lb.user_id', 'lb.score', 'lb.placement')
            ->orderBy('lb.placement')
            ->paginate($perPage);

        $users = User::whereIn('discord_id', $results->pluck('user_id'))
            ->get()
            ->keyBy('discord_id');

        $entries = [];
        foreach ($results->items() as $row) {
            $user = $users->get($row->user_id);

            $entry = [
                'score' => (float) $row->score,
                'placement' => (int) $row->placement,
                'user' => $user->toArray(),
            ];

            if ($includeUserFlair && $user) {
                $user->appendFlair();
                $userService = app(UserService::class);
                $userService->refreshUserCache($user);
                $entry['user'] = $user->toArray();
            }

            $entries[] = $entry;
        }

        return response()->json([
            'data' => $entries,
            'meta' => [
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
            ],
        ]);
    }
}
