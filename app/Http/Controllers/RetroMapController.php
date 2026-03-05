<?php

namespace App\Http\Controllers;

use App\Http\Requests\RetroMap\IndexRetroMapRequest;
use App\Http\Requests\RetroMap\RetroMapRequest;
use App\Models\MapListMeta;
use App\Models\RetroMap;
use App\Services\RetroMapService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RetroMapController
{
    public function __construct(
        private RetroMapService $retroMapService
    ) {
    }

    /**
     * Get a paginated list of retro maps.
     *
     * @OA\Get(
     *     path="/maps/retro",
     *     summary="Get list of retro maps",
     *     description="Retrieves a paginated list of retro maps with optional filters. Public access.",
     *     tags={"Retro Maps"},
     *     @OA\Parameter(name="game_id", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexRetroMapRequest/properties/game_id")),
     *     @OA\Parameter(name="category_id", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexRetroMapRequest/properties/category_id")),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexRetroMapRequest/properties/page")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexRetroMapRequest/properties/per_page")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/RetroMap")),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
     *         )
     *     )
     * )
     */
    public function index(IndexRetroMapRequest $request)
    {
        $validated = $request->validated();

        $query = RetroMap::with('game')
            ->when(isset($validated['game_id']), function ($q) use ($validated) {
                return $q->whereHas('game', function ($subQ) use ($validated) {
                    $subQ->where('game_id', $validated['game_id']);
                });
            })
            ->when(isset($validated['category_id']), function ($q) use ($validated) {
                return $q->whereHas('game', function ($subQ) use ($validated) {
                    $subQ->where('category_id', $validated['category_id']);
                });
            })
            ->orderBy('retro_game_id')
            ->orderBy('sort_order');

        $page = $validated['page'];
        $perPage = $validated['per_page'];

        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Get a single retro map by ID.
     *
     * @OA\Get(
     *     path="/maps/retro/{id}",
     *     summary="Get a single retro map",
     *     description="Retrieves a single retro map with its game relationship. Public access.",
     *     tags={"Retro Maps"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/RetroMap")),
     *     @OA\Response(response=404, description="Retro map not found")
     * )
     */
    public function show($id)
    {
        $retroMap = RetroMap::with('game')->find($id);

        if (!$retroMap) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return response()->json($retroMap);
    }

    /**
     * Store a newly created retro map.
     *
     * @OA\Post(
     *     path="/maps/retro",
     *     summary="Create a new retro map",
     *     description="Creates a new retro map with automatic sort_order reordering. Requires create:retro_map permission.",
     *     tags={"Retro Maps"},
     *     security={{"discord_auth": {}}},
     *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/RetroMapRequest"))),
     *     @OA\Response(response=201, description="Retro map created successfully", @OA\JsonContent(@OA\Property(property="id", type="integer"))),
     *     @OA\Response(response=403, description="Forbidden - lacks create:retro_map permission"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function save(RetroMapRequest $request)
    {
        $user = auth()->guard('discord')->user();

        // Check permission
        $userFormatIds = $user->formatsWithPermission('create:retro_map');
        if (empty($userFormatIds)) {
            return response()->json(['message' => 'Forbidden - You do not have permission to create retro maps'], 403);
        }

        $validated = $request->validated();

        // Validate sort_order max
        $this->retroMapService->validateSortOrderMax(
            null,
            $validated['sort_order'],
            $validated['retro_game_id']
        );

        return DB::transaction(function () use ($validated) {
            // Shift existing maps to make room for new map
            $this->retroMapService->shiftMapOrder(
                $validated['retro_game_id'],
                null, // oldPosition (insert)
                $validated['sort_order'] // newPosition
            );

            $retroMap = RetroMap::create([
                'name' => $validated['name'],
                'sort_order' => $validated['sort_order'],
                'preview_url' => $validated['preview_url'],
                'retro_game_id' => $validated['retro_game_id'],
            ]);

            return response()->json(['id' => $retroMap->id], 201);
        });
    }

    /**
     * Update the specified retro map.
     *
     * @OA\Put(
     *     path="/maps/retro/{id}",
     *     summary="Update a retro map",
     *     description="Updates a retro map with automatic sort_order reordering. Requires edit:retro_map permission.",
     *     tags={"Retro Maps"},
     *     security={{"discord_auth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/RetroMapRequest"))),
     *     @OA\Response(response=204, description="Retro map updated successfully"),
     *     @OA\Response(response=403, description="Forbidden - lacks edit:retro_map permission"),
     *     @OA\Response(response=404, description="Retro map not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(RetroMapRequest $request, $id)
    {
        $user = auth()->guard('discord')->user();

        // Check permission
        $userFormatIds = $user->formatsWithPermission('edit:retro_map');
        if (empty($userFormatIds)) {
            return response()->json(['message' => 'Forbidden - You do not have permission to edit retro maps'], 403);
        }

        $retroMap = RetroMap::find($id);
        if (!$retroMap) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $validated = $request->validated();

        return DB::transaction(function () use ($validated, $retroMap) {
            $oldSortOrder = $retroMap->sort_order;
            $oldRetroGameId = $retroMap->retro_game_id;
            $newSortOrder = $validated['sort_order'];
            $newRetroGameId = $validated['retro_game_id'];

            // Validate sort_order
            $this->retroMapService->validateSortOrderMax(
                $retroMap,
                $newSortOrder,
                $newRetroGameId
            );

            // Handle reordering
            if ($newRetroGameId === $oldRetroGameId && $newSortOrder === $oldSortOrder) {
                // No reordering needed
            } elseif ($newRetroGameId === $oldRetroGameId) {
                // Same scope: reorder within same game
                $this->retroMapService->shiftMapOrder(
                    $oldRetroGameId,
                    $oldSortOrder,
                    $newSortOrder,
                    $retroMap->id
                );
            } else {
                // Different scope: move to different game
                // First, shift down old scope (remove from old position)
                $this->retroMapService->shiftMapOrder(
                    $oldRetroGameId,
                    $oldSortOrder,
                    null // newPosition (delete from old scope)
                );
                // Then, shift up new scope (insert at new position)
                $this->retroMapService->shiftMapOrder(
                    $newRetroGameId,
                    null, // oldPosition (insert in new scope)
                    $newSortOrder
                );
            }

            // Update fields
            $retroMap->name = $validated['name'];
            $retroMap->sort_order = $validated['sort_order'];
            $retroMap->preview_url = $validated['preview_url'];
            $retroMap->retro_game_id = $validated['retro_game_id'];
            $retroMap->save();

            return response()->noContent();
        });
    }

    /**
     * Soft delete the specified retro map.
     *
     * @OA\Delete(
     *     path="/maps/retro/{id}",
     *     summary="Delete a retro map",
     *     description="Soft-deletes a retro map. Fails if the map is referenced by an active map_list_meta.remake_of. Requires delete:retro_map permission.",
     *     tags={"Retro Maps"},
     *     security={{"discord_auth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Retro map deleted successfully"),
     *     @OA\Response(response=403, description="Forbidden - lacks delete:retro_map permission"),
     *     @OA\Response(response=404, description="Retro map not found"),
     *     @OA\Response(response=422, description="Retro map is referenced by active maps")
     * )
     */
    public function destroy($id)
    {
        $user = auth()->guard('discord')->user();
        $now = Carbon::now();

        // Check permission
        $userFormatIds = $user->formatsWithPermission('delete:retro_map');
        if (empty($userFormatIds)) {
            return response()->json(['message' => 'Forbidden - You do not have permission to delete retro maps'], 403);
        }

        $retroMap = RetroMap::find($id);
        if (!$retroMap) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return DB::transaction(function () use ($retroMap, $now) {
            // Check for active remake_of references
            $activeMetas = MapListMeta::activeAtTimestamp($now)
                ->where('remake_of', $retroMap->id)
                ->whereNull('deleted_on')
                ->get();

            if ($activeMetas->isNotEmpty()) {
                $mapCodes = $activeMetas->pluck('code')->toArray();
                return response()->json([
                    'message' => 'Cannot delete retro map referenced by active maps',
                    'map_codes' => $mapCodes,
                ], 422);
            }

            // Soft delete
            $retroMap->delete();

            // Shift down remaining maps to fill the gap
            $this->retroMapService->shiftMapOrder(
                $retroMap->retro_game_id,
                $retroMap->sort_order, // oldPosition
                null // newPosition (delete)
            );

            return response()->noContent();
        });
    }
}
