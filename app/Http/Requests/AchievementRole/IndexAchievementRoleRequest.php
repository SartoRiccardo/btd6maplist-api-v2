<?php

namespace App\Http\Requests\AchievementRole;

use App\Http\Requests\BaseRequest;

/**
 * @OA\Schema(
 *     schema="IndexAchievementRoleRequest",
 *     @OA\Property(property="page", type="integer", minimum=1, example=1, description="Page number"),
 *     @OA\Property(property="per_page", type="integer", minimum=1, maximum=100, example=15, description="Items per page"),
 *     @OA\Property(property="format_id", type="integer", description="Filter by leaderboard format ID"),
 *     @OA\Property(property="type", type="string", description="Filter by leaderboard type")
 * )
 */
class IndexAchievementRoleRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'format_id' => ['nullable', 'integer', 'exists:formats,id'],
            'type' => ['nullable', 'string', 'max:255'],
        ];
    }
}
