<?php

namespace App\Models;

use App\Traits\TestableStructure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Schema(
 *     schema="AchievementRole",
 *     type="object",
 *     @OA\Property(property="id", type="integer", description="The achievement role ID"),
 *     @OA\Property(property="lb_format", type="integer", description="The leaderboard format ID"),
 *     @OA\Property(property="lb_type", type="string", description="The leaderboard type"),
 *     @OA\Property(property="threshold", type="integer", description="The score threshold required"),
 *     @OA\Property(property="for_first", type="boolean", description="Whether this role is for first place only"),
 *     @OA\Property(property="tooltip_description", type="string", nullable=true, description="Description shown in tooltip"),
 *     @OA\Property(property="name", type="string", description="Name of the achievement role"),
 *     @OA\Property(property="clr_border", type="integer", description="Border color"),
 *     @OA\Property(property="clr_inner", type="integer", description="Inner color")
 * )
 */

/**
 * @OA\Schema(
 *     schema="AchievementRoleWithDiscordRoles",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/AchievementRole"),
 *         @OA\Schema(
 *             @OA\Property(
 *                 property="discord_roles",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="guild_id", type="string", description="Discord guild ID"),
 *                     @OA\Property(property="role_id", type="string", description="Discord role ID")
 *                 )
 *             )
 *         )
 *     }
 * )
 */
class AchievementRole extends Model
{
    use HasFactory, TestableStructure;

    protected $table = 'achievement_roles';

    public $timestamps = false;

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $fillable = [
        'id',
        'lb_format',
        'lb_type',
        'threshold',
        'for_first',
        'tooltip_description',
        'name',
        'clr_border',
        'clr_inner',
    ];

    protected $casts = [
        'lb_format' => 'integer',
        'threshold' => 'integer',
        'for_first' => 'boolean',
        'clr_border' => 'integer',
        'clr_inner' => 'integer',
    ];

    /**
     * Get the Discord roles associated with this achievement role.
     */
    public function discordRoles()
    {
        return $this->hasMany(DiscordRole::class);
    }

    /**
     * Scope to filter achievement roles by format and type.
     */
    public function scopeWithFilters($query, ?int $formatId, ?string $type)
    {
        if ($formatId) {
            $query->where('lb_format', $formatId);
        }
        if ($type) {
            $query->where('lb_type', $type);
        }
        return $query;
    }

    /**
     * Get achievement roles a user qualifies for based on their leaderboard positions.
     *
     * @param int $userId The Discord user ID
     */
    public static function forUser(int $userId)
    {
        return static::fromQuery("SELECT DISTINCT ON (ar.lb_format, ar.lb_type)
                ar.*
            FROM all_leaderboards lb
            JOIN achievement_roles ar
                ON lb.lb_format = ar.lb_format AND lb.lb_type = ar.lb_type
            WHERE lb.user_id = ?
                AND (
                    lb.score >= ar.threshold AND NOT ar.for_first
                    OR
                    lb.placement = 1 AND ar.for_first
                )
            ORDER BY
                ar.lb_format,
                ar.lb_type,
                ar.for_first DESC,
                ar.threshold DESC
            ",
            [$userId]
        );
    }

    // -- TestableStructure -- //

    protected static function defaults(array $overrides = []): array
    {
        return [
            'lb_format' => 1,
            'lb_type' => 'points',
            'threshold' => 0,
            'for_first' => false,
            'tooltip_description' => null,
            'name' => 'Test Role',
            'clr_border' => 0,
            'clr_inner' => 0,
        ];
    }

    protected static function strictFields(): array
    {
        return [
            'lb_format',
            'lb_type',
            'threshold',
            'for_first',
            'tooltip_description',
            'name',
            'clr_border',
            'clr_inner',
        ];
    }
}
