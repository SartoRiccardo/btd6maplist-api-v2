<?php

namespace App\Models;

use App\Traits\TestableStructure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     @OA\Property(property="discord_id", type="string", description="User's Discord ID", example="123456789012345678"),
 *     @OA\Property(property="name", type="string", description="User's name", example="JohnDoe123"),
 *     @OA\Property(property="is_banned", type="boolean", description="Whether the user is banned"),
 *     @OA\Property(property="avatar_url", type="string", nullable=true, description="Avatar URL from Ninja Kiwi API (only included when 'flair' is in include parameter)"),
 *     @OA\Property(property="banner_url", type="string", nullable=true, description="Banner URL from Ninja Kiwi API (only included when 'flair' is in include parameter)"),
 *     @OA\Property(
 *         property="platform_roles",
 *         type="array",
 *         description="Platform (internal) roles assigned to the user",
 *         @OA\Items(ref="#/components/schemas/PlatformRole")
 *     ),
 *     @OA\Property(
 *         property="medals",
 *         type="object",
 *         nullable=true,
 *         description="Medal statistics (only included when 'medals' is in include parameter)",
 *         @OA\Property(property="wins", type="integer", description="Number of map wins", example=42),
 *         @OA\Property(property="black_border", type="integer", description="Number of black borders", example=15),
 *         @OA\Property(property="no_geraldo", type="integer", description="Number of no geraldo runs", example=8),
 *         @OA\Property(property="current_lcc", type="integer", description="Number of current LCCs", example=3)
 *     )
 * )
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable, TestableStructure;

    public $timestamps = false;
    protected $primaryKey = 'discord_id';
    protected $keyType = 'bigint';
    public $incrementing = false;

    protected $fillable = [
        'discord_id',
        'name',
        'nk_oak',
        'has_seen_popup',
        'is_banned',
        'cached_avatar_url',
        'cached_banner_url',
        'ninjakiwi_cache_expire',
    ];

    protected $hidden = [
        'nk_oak',
        'has_seen_popup',
        'pivot',
        'cached_avatar_url',
        'cached_banner_url',
        'ninjakiwi_cache_expire',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discord_id' => 'string',
            'has_seen_popup' => 'boolean',
            'is_banned' => 'boolean',
            'ninjakiwi_cache_expire' => 'datetime',
        ];
    }

    /**
     * Get the user's roles.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
    }

    /**
     * Check if user has a specific permission, optionally for a specific format.
     * A permission applies if it's granted globally (format_id = null) or for the specific format.
     */
    public function hasPermission(string $permission, ?int $formatId = null): bool
    {
        return $this->roles()
            ->whereHas('formatPermissions', function ($query) use ($permission, $formatId) {
                $query->where('permission', $permission)
                    ->where(function ($q) use ($formatId) {
                        $q->where('format_id', $formatId)
                            ->orWhereNull('format_id');
                    });
            })
            ->exists();
    }

    /**
     * Get all format IDs where the user has a specific permission.
     *
     * @return array<int> Array of format IDs
     */
    public function formatsWithPermission(string $permission): array
    {
        return $this->roles()
            ->with('formatPermissions')
            ->whereHas('formatPermissions', function ($query) use ($permission) {
                $query->where('permission', $permission);
            })
            ->get()
            ->pluck('formatPermissions.*.format_id')
            ->unique()
            ->flatten()
            ->toArray();
    }

    /**
     * Get the user's permissions accessor.
     */
    protected function getPermissionsAttribute(): array
    {
        return $this->roles()
            ->with('formatPermissions')
            ->get()
            ->pluck('formatPermissions')
            ->flatten()
            ->filter(fn($perm) => $perm->permission !== null)
            ->map(fn($perm) => [
                'permission' => $perm->permission,
                'format_id' => $perm->format_id,
            ])
            ->toArray();
    }

    /**
     * Get the user's completions through the comp_players junction table.
     */
    public function completionMetas(): BelongsToMany
    {
        return $this->belongsToMany(CompletionMeta::class, 'comp_players', 'user_id', 'run');
    }

    /**
     * Get verifications for this user.
     */
    public function verifications(): HasMany
    {
        return $this->hasMany(Verification::class, 'user_id');
    }

    /**
     * Optimized query for player medal statistics. Ported over from the Python project.
     *
     * @param \Carbon\Carbon $timestamp
     * @return array{black_border: int, current_lcc: int, no_geraldo: int, wins: int}
     */
    public function medals(\Carbon\Carbon $timestamp): array
    {
        $activeCompletionsCte = CompletionMeta::activeAtTimestamp($timestamp);
        $activeMapsCte = MapListMeta::activeAtTimestamp($timestamp);

        $sql = "
        WITH runs_with_flags AS (
            SELECT
                r.*,
                (r.lcc_id = lccs.id AND lccs.id IS NOT NULL) AS current_lcc
            FROM ({$activeCompletionsCte->toSql()}) r
            LEFT JOIN lccs_by_map lccs
                ON lccs.id = r.lcc_id
            WHERE r.accepted_by_id IS NOT NULL
                AND r.deleted_on IS NULL
        ),
        valid_maps AS MATERIALIZED (
            SELECT *
            FROM ({$activeMapsCte->toSql()})
            WHERE deleted_on IS NULL
        ),
        medals_per_map AS (
            SELECT
                c.map_code,
                BOOL_OR(rwf.black_border) AS black_border,
                BOOL_OR(rwf.no_geraldo) AS no_geraldo,
                BOOL_OR(rwf.current_lcc) AS current_lcc
            FROM runs_with_flags rwf
            JOIN completions c
                ON c.id = rwf.completion_id
            JOIN comp_players ply
                ON ply.run = rwf.id
            JOIN valid_maps m
                ON c.map_code = m.code
            WHERE ply.user_id = ?
            GROUP BY c.map_code
        )
        SELECT
            COUNT(*) AS wins,
            COUNT(CASE WHEN black_border THEN 1 END) AS black_border,
            COUNT(CASE WHEN no_geraldo THEN 1 END) AS no_geraldo,
            COUNT(CASE WHEN current_lcc THEN 1 END) AS current_lcc
        FROM medals_per_map
        ";

        $bindings = [
            ...$activeCompletionsCte->getBindings(),
            ...$activeMapsCte->getBindings(),
            $this->discord_id,
        ];

        $result = DB::select($sql, $bindings);

        return $result ? (array) $result[0] : [
            'wins' => 0,
            'black_border' => 0,
            'no_geraldo' => 0,
            'current_lcc' => 0,
        ];
    }

    /**
     * Get the user's avatar URL from cache.
     * Returns null if not cached.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        return $this->cached_avatar_url;
    }

    /**
     * Get the user's banner URL from cache.
     * Returns null if not cached.
     */
    public function getBannerUrlAttribute(): ?string
    {
        return $this->cached_banner_url;
    }

    /**
     * Append flair (avatar and banner URLs) to this user model instance.
     * Convenience method for $user->append(['avatar_url', 'banner_url']).
     */
    public function appendFlair(): void
    {
        $this->append(['avatar_url', 'banner_url']);
    }

    // --- TestableStructure --- //

    /**
     * Get the default values for the User JSON structure.
     */
    protected static function defaults(array $overrides = []): array
    {
        return [
            'discord_id' => isset($overrides['discord_id']) ? (string) $overrides['discord_id'] : '123456789012345678',
            'name' => 'TestUser',
            'is_banned' => false,
            'roles' => [],
        ];
    }

    /**
     * Get the fields that are allowed when strict mode is enabled.
     */
    protected static function strictFields(): array
    {
        return [
            'discord_id',
            'name',
            'is_banned',
            'avatar_url',
            'banner_url',
            'roles',
            'medals',
        ];
    }
}
