<?php

namespace App\Http\Controllers;

use App\Http\Requests\RetroGame\IndexRetroGameRequest;
use App\Models\RetroGame;

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

        $games = RetroGame::query()
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $games->items(),
            'meta' => [
                'current_page' => $games->currentPage(),
                'last_page' => $games->lastPage(),
                'per_page' => $games->perPage(),
                'total' => $games->total(),
            ],
        ]);
    }
}
