<?php

namespace App\Services;

use App\Models\AchievementRole;
use App\Models\DiscordRole;
use Illuminate\Validation\ValidationException;

class AchievementRoleService
{
    /**
     * Validate that the composite key (format, type, threshold) is unique.
     *
     * @throws ValidationException
     */
    public function validateCompositeUniqueness(
        int $lbFormat,
        string $lbType,
        int $threshold,
        ?int $excludeId = null
    ): void {
        $query = AchievementRole::where('lb_format', $lbFormat)
            ->where('lb_type', $lbType)
            ->where('threshold', $threshold);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'threshold' => 'An achievement role with this format, type, and threshold already exists.',
            ]);
        }
    }

    /**
     * Validate that only one role has for_first=true for a given format/type combo.
     *
     * @throws ValidationException
     */
    public function validateForFirstUniqueness(
        int $lbFormat,
        string $lbType,
        ?int $excludeId = null
    ): void {
        $query = AchievementRole::where('lb_format', $lbFormat)
            ->where('lb_type', $lbType)
            ->where('for_first', true);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'for_first' => 'Only one role can be marked as for_first=true for this format/type combination.',
            ]);
        }
    }

    /**
     * Validate that discord role_ids don't already exist in the database.
     *
     * @param array<int, array{guild_id: string, role_id: string}> $discordRoles
     * @param int|null $excludeAchievementRoleId Exclude roles belonging to this achievement role
     * @throws ValidationException
     */
    public function validateRoleIdsNotInUse(array $discordRoles, ?int $excludeAchievementRoleId = null): void
    {
        if (empty($discordRoles)) {
            return;
        }

        $roleIds = array_column($discordRoles, 'role_id');
        $query = DiscordRole::whereIn('role_id', $roleIds);

        if ($excludeAchievementRoleId !== null) {
            $query->where('achievement_role_id', '!=', $excludeAchievementRoleId);
        }

        $existingRoleIds = $query->pluck('role_id')->toArray();

        if (!empty($existingRoleIds)) {
            // Build errors keyed by the discord_roles array index
            $errors = [];
            foreach ($discordRoles as $index => $role) {
                if (in_array($role['role_id'], $existingRoleIds)) {
                    $errors["discord_roles.{$index}.role_id"] = "This role_id is already in use.";
                }
            }

            throw ValidationException::withMessages($errors);
        }
    }
}
