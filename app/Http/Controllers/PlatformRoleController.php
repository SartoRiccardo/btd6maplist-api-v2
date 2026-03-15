<?php

namespace App\Http\Controllers;

use App\Http\Requests\Role\IndexPlatformRoleRequest;
use App\Models\Role;

class PlatformRoleController extends Controller
{
    /**
     * @OA\Get(
     *     path="/roles/platform",
     *     summary="List platform roles",
     *     description="Returns a paginated list of platform (internal) roles with their grant permissions.",
     *     tags={"Roles"},
     *     @OA\Parameter(ref="#/components/parameters/IndexPlatformRoleRequest_page"),
     *     @OA\Parameter(ref="#/components/parameters/IndexPlatformRoleRequest_per_page"),
     *     @OA\Response(
     *         response="200",
     *         description="Paginated list of platform roles",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(ref="#/components/schemas/Role")
     *                 ),
     *                 @OA\Property(
     *                     property="meta",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer"),
     *                     @OA\Property(property="last_page", type="integer"),
     *                     @OA\Property(property="per_page", type="integer"),
     *                     @OA\Property(property="total", type="integer")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(IndexPlatformRoleRequest $request)
    {
        $validated = $request->validated();

        $page = $validated['page'];
        $perPage = $validated['per_page'];

        $roles = Role::query()
            ->with('canGrant')
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);

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
}
