<?php

namespace App\Http\Controllers;

use App\Http\Requests\RetroGame\IndexRetroGameRequest;
use App\Models\MapListMeta;
use App\Models\RetroGame;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RetroGameController extends Controller
{
    /**
     * Get paginated list of retro games.
     *
     * @OA\Get(
     *     path="/retro-games",
     *     summary="Get retro games",
     *     description="Retrieves paginated retro games used in Nostalgia Pack and other retro features. Public access.",
     *     tags={"Retro Games"},
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexRetroGameRequest/properties/page")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexRetroGameRequest/properties/per_page")),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated retro games",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/RetroGame")),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(IndexRetroGameRequest $request)
    {
        $validated = $request->validated();

        $page = $validated['page'];
        $perPage = $validated['per_page'];
        $include = $validated['include'] ?? [];
        $includeProgress = in_array('progress', $include);

        $games = RetroGame::query()->orderBy('id')->paginate($perPage, ['*'], 'page', $page);

        $data = collect($games->items());

        if ($includeProgress) {
            $gameIds = $data->pluck('id');
            $now = Carbon::now();
            $activeMetaCte = MapListMeta::activeAtTimestamp($now);

            $progress = DB::table('retro_maps as rm')
                ->select('rm.retro_game_id')
                ->selectRaw('COUNT(rm.id) AS total_maps')
                ->selectRaw('COUNT(active_meta.remake_of) AS maps_remade')
                ->leftJoin(
                    DB::raw("({$activeMetaCte->toSql()}) AS active_meta"),
                    function ($join) {
                        $join->on('active_meta.remake_of', '=', 'rm.id')
                            ->whereNull('active_meta.deleted_on');
                    }
                )
                ->addBinding($activeMetaCte->getBindings(), 'join')
                ->whereIn('rm.retro_game_id', $gameIds)
                ->whereNull('rm.deleted_at')
                ->groupBy('rm.retro_game_id')
                ->get()
                ->keyBy('retro_game_id');

            $data = $data->map(function ($game) use ($progress) {
                $p = $progress->get($game->id);
                $game->total_maps = $p ? (int) $p->total_maps : 0;
                $game->maps_remade = $p ? (int) $p->maps_remade : 0;
                return $game;
            });
        }

        return response()->json([
            'data' => $data->values(),
            'meta' => [
                'current_page' => $games->currentPage(),
                'last_page' => $games->lastPage(),
                'per_page' => $games->perPage(),
                'total' => $games->total(),
            ],
        ]);
    }
}
