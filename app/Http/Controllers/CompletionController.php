<?php

namespace App\Http\Controllers;

use App\Http\Requests\Completion\IndexCompletionRequest;
use App\Http\Requests\Completion\StoreCompletionRequest;
use App\Http\Requests\Completion\UpdateCompletionRequest;
use App\Jobs\SendCompletionSubmissionWebhookJob;
use App\Jobs\UpdateCompletionWebhookJob;
use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\LeastCostChimps;
use App\Models\MapListMeta;
use App\Services\CompletionSubmission\CompletionSubmissionValidatorFactory;
use App\Services\CompletionService;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CompletionController
{
    /**
     * Get a paginated list of completions.
     *
     * @OA\Get(
     *     path="/completions",
     *     summary="Get list of completions",
     *     description="Retrieves a paginated list of completions with optional filters. Completions are queried based on their metadata (CompletionMeta) active at the specified timestamp.",
     *     tags={"Completions"},
     *     @OA\Parameter(name="timestamp", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexCompletionRequest/properties/timestamp")),
     *     @OA\Parameter(name="format_id", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexCompletionRequest/properties/format_id")),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexCompletionRequest/properties/page")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexCompletionRequest/properties/per_page")),
     *     @OA\Parameter(name="player_id", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexCompletionRequest/properties/player_id")),
     *     @OA\Parameter(name="map_code", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexCompletionRequest/properties/map_code")),
     *     @OA\Parameter(name="deleted", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexCompletionRequest/properties/deleted")),
     *     @OA\Parameter(name="pending", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexCompletionRequest/properties/pending")),
     *     @OA\Parameter(name="no_geraldo", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexCompletionRequest/properties/no_geraldo")),
     *     @OA\Parameter(name="lcc", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexCompletionRequest/properties/lcc")),
     *     @OA\Parameter(name="black_border", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexCompletionRequest/properties/black_border")),
     *     @OA\Parameter(name="sort_by", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexCompletionRequest/properties/sort_by")),
     *     @OA\Parameter(name="sort_order", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexCompletionRequest/properties/sort_order")),
     *     @OA\Parameter(name="include", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexCompletionRequest/properties/include")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Completion")),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(IndexCompletionRequest $request)
    {
        $validated = $request->validated();

        // Convert unix timestamp to Carbon instance for database queries
        $timestamp = Carbon::createFromTimestamp($validated['timestamp']);
        $formatId = $validated['format_id'] ?? null;
        $page = $validated['page'];
        $perPage = $validated['per_page'];
        $deleted = $validated['deleted'] ?? 'exclude';
        $pending = $validated['pending'] ?? 'exclude';
        $noGeraldo = $validated['no_geraldo'] ?? 'any';
        $lcc = $validated['lcc'] ?? 'any';
        $blackBorder = $validated['black_border'] ?? 'any';
        $playerId = $validated['player_id'] ?? null;
        $mapCode = $validated['map_code'] ?? null;
        $sortBy = $validated['sort_by'] ?? 'created_on';
        $sortOrder = $validated['sort_order'] ?? 'asc';
        $include = $validated['include'] ?? [];

        // Build query for CompletionMeta to get active completion IDs
        $latestMetaCte = CompletionMeta::activeAtTimestamp($timestamp);
        $metaQuery = CompletionMeta::from(DB::raw("({$latestMetaCte->toSql()}) as completions_meta"))
            ->setBindings($latestMetaCte->getBindings())
            ->with(['completion.map', 'completion.proofs', 'lcc', 'players']);

        // Apply deleted filter
        if ($deleted === 'only') {
            $metaQuery->whereNotNull('deleted_on');
        } elseif ($deleted === 'exclude') {
            $metaQuery->where(function ($query) use ($timestamp) {
                $query->whereNull('deleted_on')
                    ->orWhere('deleted_on', '>', $timestamp);
            });
        }

        // Apply pending filter (accepted_by_id is null)
        if ($pending === 'only') {
            $metaQuery->whereNull('accepted_by_id');
        } elseif ($pending === 'exclude') {
            $metaQuery->whereNotNull('accepted_by_id');
        }

        // Apply no_geraldo filter
        if ($noGeraldo === 'only') {
            $metaQuery->where('no_geraldo', true);
        } elseif ($noGeraldo === 'exclude') {
            $metaQuery->where('no_geraldo', false);
        }

        // Apply lcc filter (lcc_id is not null = has LCC)
        if ($lcc === 'only') {
            $metaQuery->whereNotNull('lcc_id');
        } elseif ($lcc === 'exclude') {
            $metaQuery->whereNull('lcc_id');
        }

        // Apply black_border filter
        if ($blackBorder === 'only') {
            $metaQuery->where('black_border', true);
        } elseif ($blackBorder === 'exclude') {
            $metaQuery->where('black_border', false);
        }

        // Apply player_id filter
        if ($playerId) {
            $metaQuery->whereHas('players', function ($q) use ($playerId) {
                $q->where('discord_id', $playerId);
            });
        }

        // Apply map_code filter
        if ($mapCode) {
            $metaQuery->whereHas('completion.map', function ($q) use ($mapCode) {
                $q->where('code', $mapCode);
            });
        }

        // Apply format_id filter
        if ($formatId) {
            $metaQuery->where('format_id', $formatId);
        }

        $metaPaginated = $metaQuery->orderBy($sortBy, $sortOrder)
            ->paginate($perPage, ['*'], 'page', $page);

        // Load map metadata if requested
        $mapMetadataByKey = collect();
        if (in_array('map.metadata', $include)) {
            $latestMetaCte = MapListMeta::activeAtTimestamp($timestamp);
            $mapCodes = $metaPaginated->pluck('completion.map.code')->unique()->filter();

            $mapMetadataByKey = MapListMeta::from(DB::raw("({$latestMetaCte->toSql()}) as map_list_meta"))
                ->setBindings($latestMetaCte->getBindings())
                ->with(['retroMap.game'])
                ->whereIn('code', $mapCodes)
                ->get()
                ->keyBy('code');
        }

        // Load current LCC IDs for these completions
        $lccIds = $metaPaginated->pluck('lcc_id')->unique()->filter()->values();
        $currentLccIds = collect();
        if ($lccIds->isNotEmpty()) {
            $currentLccIds = DB::table('lccs_by_map')
                ->whereIn('id', $lccIds)
                ->pluck('id');
        }

        // Build data array from paginated metas
        $data = $metaPaginated->map(function ($meta) use ($mapMetadataByKey, $currentLccIds) {
            $completion = $meta->completion;
            if (!$completion) {
                return null;
            }

            $result = [
                ...$meta->toArray(),
                ...$completion->toArray(),
                'map' => [
                    ...($mapMetadataByKey->get($completion->map->code) ?? collect())->toArray(),
                    ...$completion->map->toArray(),
                ],
                'is_current_lcc' => $meta->lcc_id ? $currentLccIds->contains($meta->lcc_id) : false,
            ];

            return $result;
        })
            ->filter()
            ->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $metaPaginated->currentPage(),
                'last_page' => $metaPaginated->lastPage(),
                'per_page' => $metaPaginated->perPage(),
                'total' => $metaPaginated->total(),
            ],
        ]);
    }

    /**
     * Get a single completion by ID.
     *
     * @OA\Get(
     *     path="/completions/{id}",
     *     summary="Get a single completion",
     *     description="Retrieves a single completion with its metadata active at the specified timestamp.",
     *     tags={"Completions"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The completion ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="timestamp",
     *         in="query",
     *         required=false,
     *         description="Unix timestamp to query the completion's metadata at. Defaults to current time.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="include",
     *         in="query",
     *         required=false,
     *         description="Comma-separated list of additional data to include. Use 'map.metadata' to include map metadata at the timestamp, 'players.flair' to include player avatar and banner URLs from Ninja Kiwi, 'accepted_by.flair' to include accepter avatar and banner URLs from Ninja Kiwi.",
     *         @OA\Schema(type="string", example="map.metadata,players.flair,accepted_by.flair")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(ref="#/components/schemas/Completion")
     *     ),
     *     @OA\Response(response=404, description="Completion not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function show(Request $request, $id)
    {
        $validated = $request->validate([
            'timestamp' => 'nullable|integer|min:0',
            'include' => 'nullable|string',
        ]);

        $timestamp = Carbon::createFromTimestamp($validated['timestamp'] ?? Carbon::now()->unix());
        $include = array_filter(explode(',', $validated['include'] ?? ''));
        $includeMapMetadata = in_array('map.metadata', $include);
        $includePlayersFlair = in_array('players.flair', $include);
        $includeAcceptedByFlair = in_array('accepted_by.flair', $include);

        // Get the completion
        $completion = Completion::with(['map', 'proofs'])->find($id);
        if (!$completion) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        // Get active CompletionMeta at timestamp
        $meta = CompletionMeta::activeForCompletion($completion->id, $timestamp);
        $meta?->load(['lcc', 'players', 'acceptedBy']);

        if (!$meta) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        // Load map metadata if requested
        $mapMetadataByKey = collect();
        if ($includeMapMetadata) {
            $latestMetaCte = MapListMeta::activeAtTimestamp($timestamp);
            $mapMetadataByKey = MapListMeta::from(DB::raw("({$latestMetaCte->toSql()}) as map_list_meta"))
                ->setBindings($latestMetaCte->getBindings())
                ->with(['retroMap.game'])
                ->where('code', $completion->map->code)
                ->get()
                ->keyBy('code');
        }

        // Check if LCC is current
        $isCurrentLcc = false;
        if ($meta->lcc_id) {
            $isCurrentLcc = DB::table('lccs_by_map')
                ->where('id', $meta->lcc_id)
                ->exists();
        }

        // Build result
        $result = [
            ...$meta->toArray(),
            ...$completion->toArray(),
            'map' => [
                ...($mapMetadataByKey->get($completion->map->code) ?? collect())->toArray(),
                ...$completion->map->toArray(),
            ],
            'is_current_lcc' => $isCurrentLcc,
        ];

        // Replace accepted_by (string ID) with full user object
        if ($meta->accepted_by_id) {
            $acceptedBy = $meta->getRelationValue('acceptedBy');
            if ($acceptedBy) {
                // Add flair if requested
                if ($includeAcceptedByFlair) {
                    $acceptedBy->appendFlair();
                    $userService = app(UserService::class);
                    $userService->refreshUserCache($acceptedBy);
                }

                $result['accepted_by'] = $acceptedBy->toArray();
            }
        }

        // Load players with flair if requested
        if ($includePlayersFlair) {
            $players = $meta->getRelation('players') ?? collect();
            $userService = app(UserService::class);
            $result['players'] = $players->map(function ($player) use ($userService) {
                $player->appendFlair();
                $userService->refreshUserCache($player);

                return $player->toArray();
            });
        }

        return response()->json($result);
    }

    /**
     * Submit a new completion for review.
     *
     * @OA\Post(
     *     path="/completions/submit",
     *     summary="Submit a completion",
     *     description="Creates a pending completion (not auto-accepted). Requires create:completion_submission permission. Validations are performed based on the format's rules. Use multipart/form-data for file uploads.",
     *     tags={"Completions"},
     *     security={{"discord_auth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(ref="#/components/schemas/StoreCompletionRequest")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Completion submitted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="The created completion ID", example=123)
     *         )
     *     ),
     *     @OA\Response(response=403, description="Forbidden - Permission or validation violation"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function submit(StoreCompletionRequest $request, CompletionService $service)
    {
        $user = auth()->guard('discord')->user();
        $data = $request->validated();

        // Add proof_images files to data array for CompletionService
        $data['proof_images'] = $request->file('proof_images', []);

        // Run format-specific validation
        $validator = app(CompletionSubmissionValidatorFactory::class)->getValidator($data['format_id']);
        $validator->validate($data, $user);

        // Create the completion (not auto-accepted)
        $result = $service->create($data, $user, autoAccept: false);

        // Dispatch webhook job
        SendCompletionSubmissionWebhookJob::dispatch($result['completion_id']);

        return response()->json(['id' => $result['completion_id']], 201);
    }

    /**
     * Store a newly created completion in storage.
     *
     * @OA\Post(
     *     path="/completions",
     *     summary="Create a new completion",
     *     description="Creates and auto-accepts a completion with its metadata. Admin-only endpoint requiring edit:completion permission. Use multipart/form-data for file uploads.",
     *     tags={"Completions"},
     *     security={{"discord_auth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(ref="#/components/schemas/StoreCompletionRequest")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Completion created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="The created completion ID", example=123)
     *         )
     *     ),
     *     @OA\Response(response=403, description="Forbidden - Permission or business rule violation"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function save(StoreCompletionRequest $request, CompletionService $service)
    {
        $user = auth()->guard('discord')->user();
        $validated = $request->validated();

        // Business rule: Admin cannot submit a completion for themselves
        if (in_array($user->discord_id, $validated['players'])) {
            return response()->json(['message' => 'You cannot submit a completion that includes yourself.'], 422);
        }

        // Permission check: Admin must have edit:completion on the format
        $userFormatIds = $user->formatsWithPermission('edit:completion');
        $hasGlobalPermission = in_array(null, $userFormatIds, true);
        $hasFormatPermission = in_array($validated['format_id'], $userFormatIds);

        if (!$hasGlobalPermission && !$hasFormatPermission) {
            return response()->json(['message' => 'Forbidden - You do not have permission to create completions for this format.'], 403);
        }

        // Add proof_images files to data array for CompletionService
        $data = $validated;
        $data['proof_images'] = $request->file('proof_images', []);

        // Create the completion with auto-accept
        $result = $service->create($data, $user, autoAccept: true);

        return response()->json(['id' => $result['completion_id']], 201);
    }

    /**
     * Update the specified completion in storage.
     *
     * @OA\Put(
     *     path="/completions/{id}",
     *     summary="Update a completion",
     *     description="Updates a completion's metadata. Creates a new CompletionMeta record for versioning. User must have edit:completion permission on both the current and new format. Map code is immutable. Players list completely replaces existing players. LCC always creates a new record if provided. If the completion was previously accepted, the original accepter is preserved and the accept parameter is ignored.",
     *     tags={"Completions"},
     *     security={{"discord_auth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The completion ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(ref="#/components/schemas/UpdateCompletionRequest")
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Completion updated successfully"
     *     ),
     *     @OA\Response(response=403, description="Forbidden - Permission or business rule violation"),
     *     @OA\Response(response=404, description="Completion not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(UpdateCompletionRequest $request, $id)
    {
        $now = Carbon::now();
        $user = auth()->guard('discord')->user();
        $validated = $request->validated();

        // Get current active CompletionMeta
        $latestMetaCte = CompletionMeta::activeAtTimestamp($now);
        $existingMeta = CompletionMeta::from(DB::raw("({$latestMetaCte->toSql()}) as completions_meta"))
            ->setBindings($latestMetaCte->getBindings())
            ->where('completion_id', $id)
            ->first();

        if (!$existingMeta) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        // Business rule: Cannot update a deleted completion
        if ($existingMeta->deleted_on !== null) {
            return response()->json(['message' => 'Cannot update a deleted completion.'], 422);
        }

        // Permission check: Must have edit:completion on BOTH current and new format
        $userFormatIds = $user->formatsWithPermission('edit:completion');
        $hasGlobalPermission = in_array(null, $userFormatIds, true);
        $hasCurrentFormatPermission = in_array($existingMeta->format_id, $userFormatIds);
        $hasNewFormatPermission = in_array($validated['format_id'], $userFormatIds);

        $canEditCurrent = $hasGlobalPermission || $hasCurrentFormatPermission;
        $canEditNew = $hasGlobalPermission || $hasNewFormatPermission;

        if (!$canEditCurrent || !$canEditNew) {
            $missing = [];
            if (!$canEditCurrent) {
                $missing[] = 'current format';
            }
            if (!$canEditNew) {
                $missing[] = 'new format';
            }
            return response()->json(['message' => 'Forbidden - You do not have edit:completion permission for the ' . implode(' and ', $missing) . '.'], 403);
        }

        // Load existing players to check business rule
        $existingMeta->load('players');
        $existingPlayerIds = $existingMeta->players->pluck('discord_id')->toArray();

        // Business rule: User cannot modify their own completion
        if (in_array($user->discord_id, $existingPlayerIds)) {
            return response()->json(['message' => 'You cannot modify your own completion.'], 403);
        }

        // Business rule: User cannot add themselves to the players list
        if (in_array($user->discord_id, $validated['players'])) {
            return response()->json(['message' => 'You cannot add yourself to the players list.'], 403);
        }

        return DB::transaction(function () use ($validated, $user, $now, $existingMeta) {
            // Handle LCC - create new record if provided, otherwise remove
            $lccId = null;
            if (is_array($validated['lcc'])) {
                $lcc = LeastCostChimps::create([
                    'leftover' => $validated['lcc']['leftover'],
                ]);
                $lccId = $lcc->id;
            }

            // Handle acceptance - preserve existing accepter if completion was previously accepted
            $acceptedBy = $existingMeta->accepted_by_id;

            // Only process accept parameter if completion was NOT previously accepted
            if ($acceptedBy === null) {
                if ($validated['accept']) {
                    $acceptedBy = $user->discord_id;
                }
                // If accept=false, stays null
            }
            // If already accepted, $acceptedBy is preserved unchanged

            // Create new CompletionMeta (versioning)
            $newMeta = CompletionMeta::create([
                'completion_id' => $existingMeta->completion_id,
                'format_id' => $validated['format_id'],
                'black_border' => $validated['black_border'] ?? $existingMeta->black_border,
                'no_geraldo' => $validated['no_geraldo'] ?? $existingMeta->no_geraldo,
                'lcc_id' => $lccId,
                'accepted_by_id' => $acceptedBy,
                'created_on' => $now,
                'deleted_on' => null,
            ]);

            // Attach players to new meta
            $newMeta->players()->attach($validated['players']);

            // Dispatch webhook update if completion was pending and is now being accepted
            if ($existingMeta->accepted_by_id === null && $acceptedBy !== null) {
                $completion = Completion::find($existingMeta->completion_id);
                if ($completion && $completion->wh_msg_id) {
                    UpdateCompletionWebhookJob::dispatch($completion->id, fail: false);
                }
            }

            return response()->noContent();
        });
    }

    /**
     * Remove the specified completion from storage (soft delete).
     *
     * @OA\Delete(
     *     path="/completions/{id}",
     *     summary="Delete a completion",
     *     description="Soft-deletes a completion by setting its deleted_on timestamp. User must have edit:completion permission for the completion's format. Idempotent - deleting an already-deleted completion returns 204 without changes.",
     *     tags={"Completions"},
     *     security={{"discord_auth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The completion ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Completion deleted successfully"
     *     ),
     *     @OA\Response(response=403, description="Forbidden - User lacks edit:completion permission for this format"),
     *     @OA\Response(response=404, description="Completion not found")
     * )
     */
    public function destroy($id)
    {
        $now = Carbon::now();
        $user = auth()->guard('discord')->user();

        // Get user's formats with edit:completion permission
        $userFormatIds = $user->formatsWithPermission('edit:completion');

        // Get current active CompletionMeta
        $existingMeta = CompletionMeta::activeForCompletion($id, $now);

        if (!$existingMeta) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        // Permission check: Must have edit:completion on the completion's format
        $hasGlobalPermission = in_array(null, $userFormatIds, true);
        $hasFormatPermission = in_array($existingMeta->format_id, $userFormatIds, true);

        if (!$hasGlobalPermission && !$hasFormatPermission) {
            return response()->json(['message' => 'Forbidden - You do not have permission to delete completions for this format.'], 403);
        }

        // Idempotent: Only set deleted_on if it's not already set
        if ($existingMeta->deleted_on === null) {
            $existingMeta->deleted_on = $now;
            $existingMeta->save();

            // Only dispatch RED job if completion was pending
            if ($existingMeta->accepted_by_id === null) {
                $existingMeta->load('completion');
                if ($existingMeta->completion->wh_msg_id) {
                    UpdateCompletionWebhookJob::dispatch($existingMeta->completion_id, fail: true);
                }
            }
        }

        return response()->noContent();
    }
}
