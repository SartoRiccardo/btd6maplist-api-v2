<?php

namespace App\Http\Requests\RetroMap;

use App\Http\Requests\BaseRequest;

/**
 * @OA\Schema(
 *     schema="RetroMapRequest",
 *     required={"name", "sort_order", "preview_url", "retro_game_id"},
 *     @OA\Property(property="name", type="string", maxLength=255, description="Retro map name", example="Map Name"),
 *     @OA\Property(property="sort_order", type="integer", minimum=1, description="Sort order within the retro game (1-based)"),
 *     @OA\Property(property="preview_url", type="string", format="uri", maxLength=500, description="URL to preview image"),
 *     @OA\Property(property="retro_game_id", type="integer", description="ID of the retro game")
 * )
 */
class RetroMapRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:1'],
            'preview_url' => ['required', 'url', 'max:500'],
            'retro_game_id' => ['required', 'integer', 'exists:retro_games,id'],
        ];
    }
}
