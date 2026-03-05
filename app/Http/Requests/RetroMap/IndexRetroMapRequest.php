<?php

namespace App\Http\Requests\RetroMap;

use App\Http\Requests\BaseRequest;

/**
 * @OA\Schema(
 *     schema="IndexRetroMapRequest",
 *     @OA\Property(property="game_id", type="integer", description="Filter by external game_id from retro_games", example=6048),
 *     @OA\Property(property="category_id", type="integer", description="Filter by category_id from retro_games", example=888),
 *     @OA\Property(property="page", type="integer", description="Page number", example=1, minimum=1),
 *     @OA\Property(property="per_page", type="integer", description="Items per page", example=15, minimum=1, maximum=100)
 * )
 */
class IndexRetroMapRequest extends BaseRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'page' => $this->input('page', 1),
            'per_page' => $this->input('per_page', 15),
        ]);
    }

    public function rules(): array
    {
        return [
            'game_id' => ['nullable', 'integer', 'min:1'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
