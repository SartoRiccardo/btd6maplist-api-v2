<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchRequest;
use App\Models\Config;
use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\User;
use App\Models\Verification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(name="Search", description="Global search endpoints")
 */
class SearchController
{
    /**
     * Global search endpoint for users and maps.
     *
     * @OA\Get(
     *     path="/search",
     *     summary="Global search across users and maps",
     *     description="Performs fuzzy string matching across users and maps using PostgreSQL SIMILARITY() function. Results are ranked by similarity and merged into a single array.",
     *     tags={"Search"},
     *     @OA\Parameter(name="q", in="query", required=true, @OA\Schema(ref="#/components/schemas/SearchRequest/properties/q")),
     *     @OA\Parameter(name="entities", in="query", required=false, @OA\Schema(ref="#/components/schemas/SearchRequest/properties/entities")),
     *     @OA\Parameter(name="limit", in="query", required=false, @OA\Schema(ref="#/components/schemas/SearchRequest/properties/limit")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 oneOf={
     *                     @OA\Schema(
     *                         type="object",
     *                         @OA\Property(property="type", type="string", enum={"user"}),
     *                         @OA\Property(property="result", ref="#/components/schemas/User")
     *                     ),
     *                     @OA\Schema(
     *                         type="object",
     *                         @OA\Property(property="type", type="string", enum={"map"}),
     *                         @OA\Property(property="result", ref="#/components/schemas/Map")
     *                     )
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function search(SearchRequest $request)
    {
        $validated = $request->validated();
        $q = $validated['q'];
        $entities = array_map('trim', explode(',', $validated['entities']));
        $limit = $validated['limit'];

        $results = collect();

        // Search users
        if (in_array('users', $entities, true)) {
            $users = User::selectRaw('users.*, SIMILARITY(name, ?) as simil', [$q])
                ->whereRaw('SIMILARITY(name, ?) > 0.1', [$q])
                ->get()
                ->map(fn($user) => [
                    'type' => 'user',
                    'result' => collect($user->toArray())->except('simil')->toArray(),
                    'simil' => $user->simil,
                ]);

            $results = $results->concat($users);
        }

        // Search maps
        if (in_array('maps', $entities, true)) {
            $maps = Map::selectRaw('maps.*, SIMILARITY(maps.name, ?) as simil', [$q])
                ->whereRaw('SIMILARITY(maps.name, ?) > 0.1', [$q])
                ->get();

            if ($maps->isNotEmpty()) {
                // Mass load MapListMeta for all found maps at current timestamp
                $latestMetaCte = MapListMeta::activeAtTimestamp(Carbon::now());
                $mapMetadataByKey = MapListMeta::from(DB::raw("({$latestMetaCte->toSql()}) as map_list_meta"))
                    ->setBindings($latestMetaCte->getBindings())
                    ->with(['retroMap.game'])
                    ->whereIn('code', $maps->pluck('code'))
                    ->get()
                    ->keyBy('code');

                // Get all verified map codes in one query
                $currentBtd6Ver = Config::loadVars(['current_btd6_ver'])->get('current_btd6_ver');
                $verifiedMapCodes = $currentBtd6Ver !== null
                    ? Verification::getVerifiedMapCodes($currentBtd6Ver, $maps->pluck('code'))->flip()->map(fn() => true)
                    : collect(); // No config means no verifications

                $maps = $maps->map(function ($map) use ($mapMetadataByKey, $verifiedMapCodes) {
                    $result = [
                        ...collect($map->toArray())->except('simil')->toArray(),
                    ];

                    $metadata = $mapMetadataByKey->get($map->code);
                    if ($metadata) {
                        $result = [
                            ...$metadata->toArray(),
                            ...$result,
                        ];
                    }

                    $result['is_verified'] = $verifiedMapCodes->get($map->code, false);

                    return [
                        'type' => 'map',
                        'result' => $result,
                        'simil' => $map->simil,
                    ];
                });

                $results = $results->concat($maps);
            }
        }

        // Sort by similarity descending
        $results = $results->sortByDesc('simil')->values();

        // Apply limit and remove similarity scores
        return response()->json(
            $results->take($limit)->map(fn($item) => [
                'type' => $item['type'],
                'result' => $item['result'],
            ])->values()
        );
    }
}
