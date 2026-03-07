<?php

namespace App\Http\Controllers;

use App\Constants\FormatConstants;
use App\Http\Requests\Map\IndexMapRequest;
use App\Http\Requests\Map\MapRequest;
use App\Http\Requests\Map\StoreMapRequest;
use App\Models\Config;
use App\Models\CompletionMeta;
use App\Models\Creator;
use App\Models\Map;
use App\Models\MapAlias;
use App\Models\MapListMeta;
use App\Models\Verification;
use App\Services\MapService;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MapController
{
    /**
     * Get a paginated list of maps.
     *
     * @OA\Get(
     *     path="/maps",
     *     summary="Get list of maps",
     *     description="Retrieves a paginated list of maps with optional filters. Maps are queried based on their metadata (MapListMeta) active at the specified timestamp.",
     *     tags={"Maps"},
     *     @OA\Parameter(name="timestamp", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexMapRequest/properties/timestamp")),
     *     @OA\Parameter(name="format_id", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexMapRequest/properties/format_id")),
     *     @OA\Parameter(name="format_subfilter", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexMapRequest/properties/format_subfilter")),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexMapRequest/properties/page")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexMapRequest/properties/per_page")),
     *     @OA\Parameter(name="deleted", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexMapRequest/properties/deleted")),
     *     @OA\Parameter(name="created_by", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexMapRequest/properties/created_by")),
     *     @OA\Parameter(name="verified_by", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexMapRequest/properties/verified_by")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Map")),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(IndexMapRequest $request)
    {
        $validated = $request->validated();

        // Convert unix timestamp to Carbon instance
        $timestamp = Carbon::createFromTimestamp($validated['timestamp']);
        $page = $validated['page'];
        $perPage = $validated['per_page'];
        $deleted = $validated['deleted'] ?? 'exclude';
        $createdBy = $validated['created_by'] ?? null;
        $verifiedBy = $validated['verified_by'] ?? null;
        $formatId = $validated['format_id'] ?? null;
        $formatSubfilter = $validated['format_subfilter'] ?? null;

        $latsetMetaCte = MapListMeta::activeAtTimestamp($timestamp);

        $metaQuery = MapListMeta::from(DB::raw("({$latsetMetaCte->toSql()}) as map_list_meta"))
            ->setBindings($latsetMetaCte->getBindings())
            ->with(['retroMap.game'])
            ->forFormat($formatId)
            ->forFormatSubfilter($formatId, $formatSubfilter)
            ->sortForFormat($formatId);

        // Apply deleted filter
        if ($deleted === 'only') {
            $metaQuery->whereNotNull('deleted_on');
        } elseif ($deleted === 'exclude') {
            $metaQuery->where(function ($query) use ($timestamp) {
                $query->whereNull('deleted_on')
                    ->orWhere('deleted_on', '>', $timestamp);
            });
        }

        // Apply created_by filter
        if ($createdBy) {
            $metaQuery->whereHas('map.creators', function ($q) use ($createdBy) {
                $q->where('user_id', $createdBy);
            });
        }

        // Apply verified_by filter
        if ($verifiedBy) {
            $metaQuery->whereHas('map.verifications', function ($q) use ($verifiedBy) {
                $q->where('user_id', $verifiedBy);
            });
        }

        // Get distinct map codes
        $metaCodes = $metaQuery->paginate($perPage, ['*'], 'page', $page);

        $maps = Map::whereIn('code', $metaCodes->pluck('code'))
            ->get();

        // Get all verified map codes (any version)
        $verifiedMapCodes = Verification::getVerifiedMapCodes(
            Config::loadVars(['current_btd6_ver'])->get('current_btd6_ver'),
            $metaCodes->pluck('code')
        )
            ->flip()
            ->map(fn() => true);

        // Merge meta and map data for each code in pagination order
        $metasByKey = $metaCodes->keyBy('code');
        $mapsByKey = $maps->keyBy('code');
        $data = $metaCodes->pluck('code')
            ->map(function ($code) use ($metasByKey, $mapsByKey, $verifiedMapCodes) {
                $meta = $metasByKey->get($code);
                $map = $mapsByKey->get($code);

                if (!$map || !$meta) {
                    return null;
                }

                return [
                    ...$map->toArray(),
                    ...$meta->toArray(),
                    'is_verified' => $verifiedMapCodes->get($code, false),
                ];
            })
            ->filter()
            ->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $metaCodes->currentPage(),
                'last_page' => $metaCodes->lastPage(),
                'per_page' => $metaCodes->perPage(),
                'total' => $metaCodes->total(),
            ],
        ]);
    }

    /**
     * Get a single map by ID.
     *
     * @OA\Get(
     *     path="/maps/{id}",
     *     summary="Get a single map",
     *     description="Retrieves a single map with its metadata active at the specified timestamp.",
     *     tags={"Maps"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The map code",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="timestamp",
     *         in="query",
     *         required=false,
     *         description="Unix timestamp to query the map's metadata at. Defaults to current time.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="include",
     *         in="query",
     *         required=false,
     *         description="Comma-separated list of additional data to include. Use 'creators.flair' to include creator avatar and banner URLs from Ninja Kiwi, 'verifiers.flair' to include verifier avatar and banner URLs from Ninja Kiwi.",
     *         @OA\Schema(type="string", example="creators.flair,verifiers.flair")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(ref="#/components/schemas/Map")
     *     ),
     *     @OA\Response(response=404, description="Map not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function show(Request $request, $id, UserService $userService)
    {
        $validated = $request->validate([
            'timestamp' => 'nullable|integer',
            'include' => 'nullable|string',
        ]);

        $timestamp = Carbon::createFromTimestamp($validated['timestamp'] ?? Carbon::now()->unix());
        $include = array_filter(explode(',', $validated['include'] ?? ''));
        $includeCreatorsFlair = in_array('creators.flair', $include);
        $includeVerifiersFlair = in_array('verifiers.flair', $include);

        // Get the map with eager loaded relationships
        $map = Map::with('creators.user', 'verifications.user', 'aliases')->find($id);
        if (!$map) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        // Get active MapListMeta at timestamp
        $latestMetaCte = MapListMeta::activeAtTimestamp($timestamp);
        $meta = MapListMeta::from(DB::raw("({$latestMetaCte->toSql()}) as map_list_meta"))
            ->setBindings($latestMetaCte->getBindings())
            ->with(['retroMap.game'])
            ->where('code', $map->code)
            ->first();

        if (!$meta) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        // Get current BTD6 version for verifications
        $currentBtd6Ver = Config::loadVars(['current_btd6_ver'])->get('current_btd6_ver');

        // Build result
        $result = [
            ...$map->toArray(),
            ...$meta->toArray(),
            'verifications' => $map->verifications->filter(function ($v) use ($currentBtd6Ver) {
                return $v->version === null || $v->version === $currentBtd6Ver;
            }),
            'aliases' => $map->aliases->pluck('alias')->sort()->values()->toArray(),
        ];
        $result['is_verified'] = $result['verifications']->isNotEmpty();

        // Load creator flair if requested
        if ($includeCreatorsFlair) {
            $result['creators'] = $map->creators->map(function ($creator) use ($userService) {
                $user = $creator->user;
                $deco = null;
                if ($user && $user->nk_oak) {
                    $deco = $userService->getUserDeco($user->nk_oak);
                }

                return [
                    ...$creator->toArray(),
                    'avatar_url' => $deco['avatar_url'] ?? null,
                    'banner_url' => $deco['banner_url'] ?? null,
                ];
            });
        }

        // Load verification flair if requested
        if ($includeVerifiersFlair) {
            $result['verifications'] = $result['verifications']->map(function ($verification) use ($userService) {
                $user = $verification->user;
                $deco = null;
                if ($user && $user->nk_oak) {
                    $deco = $userService->getUserDeco($user->nk_oak);
                }

                return [
                    ...$verification->toArray(),
                    'avatar_url' => $deco['avatar_url'] ?? null,
                    'banner_url' => $deco['banner_url'] ?? null,
                ];
            });
        }

        return response()->json($result);
    }

    /**
     * Store a newly created map in storage.
     *
     * @OA\Post(
     *     path="/maps",
     *     summary="Create a new map",
     *     description="Creates a new map with its metadata. Fields that require specific permissions will be silently ignored if the user lacks those permissions. User must have at least one format's edit:map permission.",
     *     tags={"Maps"},
     *     security={{"discord_auth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(ref="#/components/schemas/StoreMapRequest")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Map created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="string", description="The created map code", example="TKIEXYSQ")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Forbidden - User lacks edit:map permission"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function save(StoreMapRequest $request, MapService $mapService)
    {
        $now = Carbon::now();
        $user = auth()->guard('discord')->user();

        // Get user's formats with edit:map permission
        $userFormatIds = $user->formatsWithPermission('edit:map');

        // User must have at least one edit:map permission
        if (empty($userFormatIds)) {
            return response()->json(['message' => 'Forbidden - You do not have permission to create maps'], 403);
        }

        $validated = $request->validated();
        $mapService->validatePlacementMax(
            null, // New map, no existing meta
            $validated['placement_curver'] ?? null,
            $validated['placement_allver'] ?? null,
            $now
        );

        $mapService->validateAliases(
            $validated['aliases'] ?? [],
            null, // New map, no code to ignore
            $now
        );

        // Handle custom map preview file upload
        $mapPreviewUrl = $validated['map_preview_url'] ?? null;
        if ($request->hasFile('custom_map_preview_file')) {
            $file = $request->file('custom_map_preview_file');
            $extension = $file->getClientOriginalExtension();

            Storage::disk('public')->putFileAs(
                'map_previews',
                $file,
                "{$validated['code']}.{$extension}"
            );

            $mapPreviewUrl = Storage::disk('public')->url("map_previews/{$validated['code']}.{$extension}");
        }

        // Handle r6 start file upload
        $r6StartUrl = $validated['r6_start'] ?? null;
        if ($request->hasFile('r6_start_file')) {
            $file = $request->file('r6_start_file');
            $extension = $file->getClientOriginalExtension();

            Storage::disk('public')->putFileAs(
                'r6_starts',
                $file,
                "{$validated['code']}.{$extension}"
            );

            $r6StartUrl = Storage::disk('public')->url("r6_starts/{$validated['code']}.{$extension}");
        }

        return DB::transaction(function () use ($validated, $userFormatIds, $mapService, $now, $mapPreviewUrl, $r6StartUrl) {
            // Filter meta fields based on user permissions
            $metaFields = $mapService->filterMetaFieldsByPermissions(
                $validated,
                $userFormatIds,
                null  // POST: non-permitted fields → NULL
            );

            // Validate at least one meta field is set
            $mapService->validateAtLeastOneMetaFieldIsSet($metaFields, null);

            // Create the Map
            $map = Map::create([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'r6_start' => $r6StartUrl,
                'map_data' => $validated['map_data'] ?? null,
                'map_preview_url' => $mapPreviewUrl,
                'map_notes' => $validated['map_notes'] ?? null,
            ]);

            // Create MapListMeta
            MapListMeta::create([
                'code' => $map->code,
                'placement_curver' => $metaFields['placement_curver'] ?? null,
                'placement_allver' => $metaFields['placement_allver'] ?? null,
                'difficulty' => $metaFields['difficulty'] ?? null,
                'optimal_heros' => $validated['optimal_heros'] ?? [],
                'botb_difficulty' => $metaFields['botb_difficulty'] ?? null,
                'remake_of' => $metaFields['remake_of'] ?? null,
                'created_on' => $now,
                'deleted_on' => null,
            ]);

            $mapService->rerankPlacements(
                null,
                $metaFields['placement_curver'] ?? null,
                null,
                $metaFields['placement_allver'] ?? null,
                $map->code,
                $now
            );

            // Handle remake_of cleanup
            if ($metaFields['remake_of'] !== null) {
                $mapService->clearPreviousRemakeOf($metaFields['remake_of'], $map->code, $now);
            }

            // Create creators if provided
            if (isset($validated['creators']) && is_array($validated['creators'])) {
                foreach ($validated['creators'] as $creator) {
                    Creator::create([
                        'map_code' => $map->code,
                        'user_id' => $creator['user_id'],
                        'role' => $creator['role'] ?? null,
                    ]);
                }
            }

            // Create verifiers if provided
            if (isset($validated['verifiers']) && is_array($validated['verifiers'])) {
                foreach ($validated['verifiers'] as $verifier) {
                    Verification::create([
                        'map_code' => $map->code,
                        'user_id' => $verifier['user_id'],
                        'version' => $verifier['version'] ?? null,
                    ]);
                }
            }

            // Create aliases if provided
            if (isset($validated['aliases']) && is_array($validated['aliases'])) {
                $placeholders = implode(',', array_fill(0, count($validated['aliases']), '?'));
                MapAlias::where('map_code', $map->code)
                    ->orWhereRaw("lower(alias) = ANY(ARRAY[{$placeholders}])", $validated['aliases'])
                    ->delete();

                foreach ($validated['aliases'] as $alias) {
                    MapAlias::create([
                        'alias' => $alias,
                        'map_code' => $map->code,
                    ]);
                }
            }

            return response()->json(['code' => $map->code], 201);
        });
    }

    public function submit(Request $request)
    {
        return response()->json(['message' => 'Not Implemented'], 501);
    }

    /**
     * Update the specified map in storage.
     *
     * @OA\Put(
     *     path="/maps/{id}",
     *     summary="Update a map",
     *     description="Updates a map and its metadata. Fields that require specific permissions will retain their existing values if the user lacks those permissions. User must have at least one format's edit:map permission.",
     *     tags={"Maps"},
     *     security={{"discord_auth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The map code",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(ref="#/components/schemas/UpdateMapRequest")
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Map updated successfully"
     *     ),
     *     @OA\Response(response=403, description="Forbidden - User lacks edit:map permission"),
     *     @OA\Response(response=404, description="Map not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(MapRequest $request, $id, MapService $mapService)
    {
        $now = Carbon::now();
        $user = auth()->guard('discord')->user();

        // Get user's formats with edit:map permission
        $userFormatIds = $user->formatsWithPermission('edit:map');

        // User must have at least one edit:map permission
        if (empty($userFormatIds)) {
            return response()->json(['message' => 'Forbidden - You do not have permission to edit maps'], 403);
        }

        // Find the map
        $map = Map::find($id);
        if (!$map) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        // Get current active MapListMeta
        $latestMetaCte = MapListMeta::activeAtTimestamp($now);
        $existingMeta = MapListMeta::from(DB::raw("({$latestMetaCte->toSql()}) as map_list_meta"))
            ->setBindings($latestMetaCte->getBindings())
            ->where('code', $map->code)
            ->first();

        if (!$existingMeta) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $validated = $request->validated();
        $mapService->validatePlacementMax(
            $existingMeta,
            $validated['placement_curver'] ?? null,
            $validated['placement_allver'] ?? null,
            $now
        );

        $mapService->validateAliases(
            $validated['aliases'] ?? [],
            $map->code, // Ignore this map's existing aliases
            $now
        );

        // Handle custom map preview file upload
        $mapPreviewUrl = $validated['map_preview_url'] ?? null;
        if ($request->hasFile('custom_map_preview_file')) {
            $file = $request->file('custom_map_preview_file');
            $extension = $file->getClientOriginalExtension();

            Storage::disk('public')->putFileAs(
                'map_previews',
                $file,
                "{$map->code}.{$extension}"
            );

            $mapPreviewUrl = Storage::disk('public')->url("map_previews/{$map->code}.{$extension}");
        }

        // Handle r6 start file upload
        $r6StartUrl = $validated['r6_start'] ?? null;
        if ($request->hasFile('r6_start_file')) {
            $file = $request->file('r6_start_file');
            $extension = $file->getClientOriginalExtension();

            Storage::disk('public')->putFileAs(
                'r6_starts',
                $file,
                "{$map->code}.{$extension}"
            );

            $r6StartUrl = Storage::disk('public')->url("r6_starts/{$map->code}.{$extension}");
        }

        return DB::transaction(function () use ($validated, $map, $existingMeta, $userFormatIds, $mapService, $now, $mapPreviewUrl, $r6StartUrl) {
            // Filter meta fields based on user permissions
            $metaFields = $mapService->filterMetaFieldsByPermissions(
                $validated,
                $userFormatIds,
                $existingMeta
            );

            // Validate at least one meta field is set
            $mapService->validateAtLeastOneMetaFieldIsSet($metaFields, $existingMeta);

            // Update Map fields
            $map->name = $validated['name'];
            $map->r6_start = $r6StartUrl;
            $map->map_data = $validated['map_data'] ?? null;
            $map->map_preview_url = $mapPreviewUrl;
            $map->map_notes = $validated['map_notes'] ?? null;
            $map->save();

            // Update creators
            Creator::where('map_code', $map->code)->delete();
            foreach ($validated['creators'] ?? [] as $creator) {
                Creator::create([
                    'map_code' => $map->code,
                    'user_id' => $creator['user_id'],
                    'role' => $creator['role'] ?? null,
                ]);
            }

            // Update verifiers
            Verification::where('map_code', $map->code)->whereNull('version')->delete();
            foreach ($validated['verifiers'] ?? [] as $verifier) {
                Verification::create([
                    'map_code' => $map->code,
                    'user_id' => $verifier['user_id'],
                    'version' => $verifier['version'] ?? null,
                ]);
            }

            // Update aliases
            if (isset($validated['aliases']) && is_array($validated['aliases'])) {
                $placeholders = implode(',', array_fill(0, count($validated['aliases']), '?'));
                MapAlias::where('map_code', $map->code)
                    ->orWhereRaw("lower(alias) = ANY(ARRAY[{$placeholders}])", $validated['aliases'])
                    ->delete();

                foreach ($validated['aliases'] as $alias) {
                    MapAlias::create([
                        'alias' => $alias,
                        'map_code' => $map->code,
                    ]);
                }
            }

            // Check if meta fields changed
            $permissionFields = $mapService->getPermissionFieldMapping();
            $metaFieldsChanged = false;
            foreach ($permissionFields as $field) {
                $hasField = array_key_exists($field, $metaFields);
                $newValue = $hasField ? $metaFields[$field] : $existingMeta->$field;
                if ($existingMeta->$field !== $newValue) {
                    $metaFieldsChanged = true;
                    break;
                }
            }
            $optimalHeroesChanged = array_key_exists('optimal_heros', $validated)
                && $validated['optimal_heros'] !== $existingMeta->optimal_heros;

            if ($metaFieldsChanged || $optimalHeroesChanged) {
                $getCurver = array_key_exists('placement_curver', $metaFields);
                $getAllver = array_key_exists('placement_allver', $metaFields);
                $mapService->rerankPlacements(
                    $existingMeta->placement_curver,
                    $getCurver ? $metaFields['placement_curver'] : $existingMeta->placement_curver,
                    $existingMeta->placement_allver,
                    $getAllver ? $metaFields['placement_allver'] : $existingMeta->placement_allver,
                    $map->code,
                    $now
                );

                $hasRemakeOf = array_key_exists('remake_of', $metaFields);
                $newRemakeOf = $hasRemakeOf ? $metaFields['remake_of'] : $existingMeta->remake_of;
                if ($existingMeta->remake_of !== $newRemakeOf && $newRemakeOf !== null) {
                    $mapService->clearPreviousRemakeOf($newRemakeOf, $map->code, $now);
                }

                MapListMeta::create([
                    'code' => $map->code,
                    'placement_curver' => $getCurver ? $metaFields['placement_curver'] : $existingMeta->placement_curver,
                    'placement_allver' => $getAllver ? $metaFields['placement_allver'] : $existingMeta->placement_allver,
                    'difficulty' => array_key_exists('difficulty', $metaFields) ? $metaFields['difficulty'] : $existingMeta->difficulty,
                    'optimal_heros' => array_key_exists('optimal_heros', $validated) ? $validated['optimal_heros'] : $existingMeta->optimal_heros,
                    'botb_difficulty' => array_key_exists('botb_difficulty', $metaFields) ? $metaFields['botb_difficulty'] : $existingMeta->botb_difficulty,
                    'remake_of' => $newRemakeOf,
                    'created_on' => $now,
                    'deleted_on' => null,
                ]);
            }

            return response()->noContent();
        });
    }

    /**
     * Remove the specified map from storage (soft delete via MapListMeta).
     *
     * @OA\Delete(
     *     path="/maps/{id}",
     *     summary="Delete a map",
     *     description="Soft-deletes a map by setting its metadata fields to NULL based on user permissions. If ALL list fields (placement_curver, placement_allver, difficulty, botb_difficulty, remake_of) are NULL, also sets deleted_on. User must have at least one format's edit:map permission.",
     *     tags={"Maps"},
     *     security={{"discord_auth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The map code",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Map deleted successfully"
     *     ),
     *     @OA\Response(response=403, description="Forbidden - User lacks edit:map permission"),
     *     @OA\Response(response=404, description="Map not found")
     * )
     */
    public function destroy($id, MapService $mapService)
    {
        $now = Carbon::now();
        $user = auth()->guard('discord')->user();

        // Get user's formats with edit:map permission
        $userFormatIds = $user->formatsWithPermission('edit:map');

        // User must have at least one edit:map permission
        if (empty($userFormatIds)) {
            return response()->json(['message' => 'Forbidden - You do not have permission to delete maps'], 403);
        }

        // Get current active MapListMeta
        $latestMetaCte = MapListMeta::activeAtTimestamp($now);
        $existingMeta = MapListMeta::from(DB::raw("({$latestMetaCte->toSql()}) as map_list_meta"))
            ->setBindings($latestMetaCte->getBindings())
            ->where('code', $id)
            ->first();

        if (!$existingMeta) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return DB::transaction(function () use ($id, $existingMeta, $userFormatIds, $mapService, $now) {
            // Build new meta data based on user's permissions
            // All permission-based fields are set to NULL
            // null in userFormatIds grants admin privileges (all permissions)
            $hasGlobalPermission = in_array(null, $userFormatIds, true);
            $hasMaplistPermission = $hasGlobalPermission || in_array(FormatConstants::MAPLIST, $userFormatIds);
            $hasMaplistAllPermission = $hasGlobalPermission || in_array(FormatConstants::MAPLIST_ALL_VERSIONS, $userFormatIds);
            $hasExpertListPermission = $hasGlobalPermission || in_array(FormatConstants::EXPERT_LIST, $userFormatIds);
            $hasBotbPermission = $hasGlobalPermission || in_array(FormatConstants::BEST_OF_THE_BEST, $userFormatIds);
            $hasNostalgiaPermission = $hasGlobalPermission || in_array(FormatConstants::NOSTALGIA_PACK, $userFormatIds);

            $newPlacementCurver = $hasMaplistPermission ? null : $existingMeta->placement_curver;
            $newPlacementAllver = $hasMaplistAllPermission ? null : $existingMeta->placement_allver;
            $newDifficulty = $hasExpertListPermission ? null : $existingMeta->difficulty;
            $newBotbDifficulty = $hasBotbPermission ? null : $existingMeta->botb_difficulty;
            $newRemakeOf = $hasNostalgiaPermission ? null : $existingMeta->remake_of;

            // Check if ALL 5 list fields are NULL
            $allListFieldsNull = (
                $newPlacementCurver === null &&
                $newPlacementAllver === null &&
                $newDifficulty === null &&
                $newBotbDifficulty === null &&
                $newRemakeOf === null
            );

            // Set deleted_on if ALL list fields are NULL
            $newDeletedOn = $allListFieldsNull ? $now : null;

            // Handle reranking if placements are being cleared
            $curPositionFrom = $hasMaplistPermission ? $existingMeta->placement_curver : null;
            $allPositionFrom = $hasMaplistAllPermission ? $existingMeta->placement_allver : null;

            if (
                !($existingMeta->placement_curver !== $newPlacementCurver
                    || $existingMeta->placement_allver !== $newPlacementAllver
                    || $existingMeta->difficulty !== $newDifficulty
                    || $existingMeta->botb_difficulty !== $newBotbDifficulty
                    || $existingMeta->remake_of !== $newRemakeOf)
            ) {
                return response()->noContent();
            }

            $mapService->rerankPlacements(
                $curPositionFrom,
                null,
                $allPositionFrom,
                null,
                $id,
                $now
            );

            // Handle remake_of cleanup if being cleared
            if ($hasNostalgiaPermission && $existingMeta->remake_of !== null) {
                $mapService->clearPreviousRemakeOf($existingMeta->remake_of, $id, $now);
            }

            // Create new MapListMeta
            MapListMeta::create([
                'code' => $id,
                'placement_curver' => $newPlacementCurver,
                'placement_allver' => $newPlacementAllver,
                'difficulty' => $newDifficulty,
                'optimal_heros' => $existingMeta->optimal_heros,
                'botb_difficulty' => $newBotbDifficulty,
                'remake_of' => $newRemakeOf,
                'created_on' => $now,
                'deleted_on' => $newDeletedOn,
            ]);

            return response()->noContent();
        });
    }

    /**
     * Transfer completions from one map to another.
     *
     * @OA\Put(
     *     path="/maps/{code}/completions/transfer",
     *     summary="Transfer completions from one map to another",
     *     description="Bulk-transfers completions from source map to target map. User must have edit:completion permission for the completion's format (or global permission). Original completions are soft-deleted. Operation is atomic - either all completions transfer or none do.",
     *     tags={"Maps", "Completions"},
     *     security={{"discord_auth": {}}},
     *     @OA\Parameter(
     *         name="code",
     *         in="path",
     *         required=true,
     *         description="Source map code",
     *         @OA\Schema(type="string", example="TKIEXYSQ")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"target_map_code"},
     *                 @OA\Property(property="target_map_code", type="string", example="TKIEXYSQ")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Completions transferred successfully"
     *     ),
     *     @OA\Response(response=403, description="Forbidden - No edit:completion permission"),
     *     @OA\Response(response=404, description="Source map not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function transferCompletions(Request $request, $code)
    {
        $now = Carbon::now();
        $user = auth()->guard('discord')->user();

        // Validate request
        $validated = $request->validate([
            'target_map_code' => ['required', 'string', 'max:10'],
        ]);

        // Validate source and target are not the same
        if ($code === $validated['target_map_code']) {
            return response()->json(['errors' => ['target_map_code' => ['Target map cannot be the same as source map.']]], 422);
        }

        // Check if source map exists
        $sourceMap = Map::find($code);
        if (!$sourceMap) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        // Check if target map exists
        $targetMap = Map::find($validated['target_map_code']);
        if (!$targetMap) {
            return response()->json(['message' => 'The selected target map code is invalid.'], 422);
        }

        // Get user's permissions for edit:completion
        $userFormatIds = $user->formatsWithPermission('edit:completion');
        $hasGlobalPermission = in_array(null, $userFormatIds, true);

        // Check if user has any edit:completion permission
        if (!$hasGlobalPermission && empty(array_filter($userFormatIds))) {
            return response()->json(['message' => 'Forbidden - You do not have permission to transfer completions.'], 403);
        }

        // Build format_ids array for SQL binding
        $formatIds = [];
        if (!$hasGlobalPermission) {
            $formatIds = array_filter($userFormatIds); // Remove null values
        }

        // Execute atomic transfer transaction
        DB::transaction(function () use ($code, $validated, $now, $formatIds) {
            // Get the latest_completions CTE using the model method
            $latestMetaQuery = CompletionMeta::activeAtTimestamp($now);
            $latestMetaSql = $latestMetaQuery->toSql();
            $latestMetaBindings = $latestMetaQuery->getBindings();

            // Build SQL based on whether we're filtering by format
            $formatFilter = '';
            $additionalParams = [];

            if (!empty($formatIds)) {
                // Filter by format_ids
                $placeholders = implode(',', array_fill(0, count($formatIds), '?'));
                $formatFilter = 'AND cm.format_id IN (' . $placeholders . ')';
                $additionalParams = $formatIds;
            }

            $params = [
                ...$latestMetaBindings,
                $validated['target_map_code'],
                $code,
                ...$additionalParams,
                $now,
                ...$additionalParams,
                $now,
            ];

            $sql = "
            WITH latest_completions AS (
                {$latestMetaSql}
            ),
            copied_completions AS (
                INSERT INTO completions
                    (map_code, submitted_on, subm_notes, subm_wh_payload, copied_from_id)
                SELECT
                    ?, c.submitted_on, c.subm_notes, c.subm_wh_payload, c.id
                FROM completions c
                JOIN latest_completions cm
                    ON c.id = cm.completion_id
                WHERE c.map_code = ?
                    AND cm.deleted_on IS NULL
                    {$formatFilter}
                RETURNING id AS new_id, copied_from_id AS old_id
            ),
            copied_completion_metas AS (
                INSERT INTO completions_meta
                    (completion_id, black_border, no_geraldo, lcc_id, accepted_by_id, format_id, created_on, copied_from_id)
                SELECT
                    cc.new_id, cm.black_border, cm.no_geraldo, cm.lcc_id, cm.accepted_by_id, cm.format_id, ?, cm.id
                FROM latest_completions cm
                JOIN copied_completions cc
                    ON cm.completion_id = cc.old_id
                WHERE cm.deleted_on IS NULL
                    {$formatFilter}
                RETURNING id AS new_id, copied_from_id AS old_id
            ),
            delete_old_completions AS (
                UPDATE completions_meta cm
                SET
                    deleted_on = ?
                FROM copied_completion_metas ccm
                WHERE ccm.old_id = cm.id
            ),
            copied_users AS (
                INSERT INTO comp_players
                    (user_id, run)
                SELECT
                    cp.user_id, ccm.new_id
                FROM comp_players cp
                JOIN copied_completion_metas ccm
                    ON ccm.old_id = cp.run
                RETURNING user_id
            )
            SELECT * FROM latest_completions
            ";

            DB::statement($sql, $params);
        });

        return response()->noContent();
    }
}
