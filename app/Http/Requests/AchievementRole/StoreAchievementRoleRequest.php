<?php

namespace App\Http\Requests\AchievementRole;

use App\Http\Requests\BaseRequest;

/**
 * @OA\Schema(
 *     schema="StoreAchievementRoleRequest",
 *     required={"lb_format", "lb_type", "threshold", "for_first", "name", "clr_border", "clr_inner"},
 *     @OA\Property(property="lb_format", type="integer", description="Leaderboard format ID"),
 *     @OA\Property(property="lb_type", type="string", maxLength=255, description="Leaderboard type"),
 *     @OA\Property(property="threshold", type="integer", minimum=0, description="Score threshold"),
 *     @OA\Property(property="for_first", type="boolean", description="Whether this role is for first place only"),
 *     @OA\Property(property="tooltip_description", type="string", maxLength=128, nullable=true, description="Description shown in tooltip"),
 *     @OA\Property(property="name", type="string", maxLength=32, description="Name of the achievement role"),
 *     @OA\Property(property="clr_border", type="integer", description="Border color"),
 *     @OA\Property(property="clr_inner", type="integer", description="Inner color"),
 *     @OA\Property(
 *         property="discord_roles",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             required={"guild_id", "role_id"},
 *             @OA\Property(property="guild_id", type="string", description="Discord guild ID"),
 *             @OA\Property(property="role_id", type="string", description="Discord role ID")
 *         )
 *     )
 * )
 */
class StoreAchievementRoleRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'lb_format' => ['required', 'integer', 'exists:formats,id'],
            'lb_type' => ['required', 'string', 'max:255'],
            'threshold' => ['required', 'integer', 'min:0'],
            'for_first' => ['required', 'boolean'],
            'tooltip_description' => ['nullable', 'string', 'max:128'],
            'name' => ['required', 'string', 'max:32'],
            'clr_border' => ['required', 'integer'],
            'clr_inner' => ['required', 'integer'],
            'discord_roles' => ['nullable', 'array'],
            'discord_roles.*.guild_id' => ['required', 'string'],
            'discord_roles.*.role_id' => ['required', 'string'],
        ];
    }

    protected function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $discordRoles = $this->input('discord_roles', []);

            // Rule: No duplicate role_ids in payload
            $roleIds = collect($discordRoles)->pluck('role_id');
            $duplicates = collect($discordRoles)
                ->map(fn($role, $idx) => ['idx' => $idx, 'role_id' => $role['role_id']])
                ->groupBy('role_id')
                ->filter(fn($group) => $group->count() > 1);

            if ($duplicates->isNotEmpty()) {
                foreach ($duplicates as $roleGroup) {
                    foreach ($roleGroup as $item) {
                        $validator->errors()->add("discord_roles.{$item['idx']}.role_id", 'Duplicate role_id in payload.');
                    }
                }
            }
        });
    }
}
