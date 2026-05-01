<?php

namespace App\Http\Requests\RetroMap;

use App\Http\Requests\BaseRequest;

/**
 * @OA\Schema(
 *     schema="RetroMapRequest",
 *     required={"name", "sort_order", "retro_game_id"},
 *     @OA\Property(property="name", type="string", maxLength=255, description="Retro map name", example="Map Name"),
 *     @OA\Property(property="sort_order", type="integer", minimum=1, description="Sort order within the retro game (1-based)"),
 *     @OA\Property(property="preview_url", type="string", format="uri", maxLength=500, nullable=true, description="URL to preview image (required if preview_file is not provided)"),
 *     @OA\Property(property="retro_game_id", type="integer", description="ID of the retro game"),
 *     @OA\Property(property="preview_file", type="string", format="binary", description="Preview image file (max 4.5MB, valid extensions: jpg, jpeg, png, gif, webp)")
 * )
 */
class RetroMapRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:1'],
            'preview_url' => ['nullable', 'url', 'max:500', 'required_without:preview_file'],
            'preview_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:4500'],
            'retro_game_id' => ['required', 'integer', 'exists:retro_games,id'],
        ];
    }
}
