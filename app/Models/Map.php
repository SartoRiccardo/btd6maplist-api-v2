<?php

namespace App\Models;

use App\Traits\TestableStructure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @OA\Schema(
 *     schema="Map",
 *     type="object",
 *     @OA\Property(property="code", type="string", description="Unique map code", example="TKIEXYSQ"),
 *     @OA\Property(property="name", type="string", description="Map name", example="In The Loop"),
 *     @OA\Property(property="r6_start", type="integer", nullable=true, description="BTD6 version when map was added", example=10),
 *     @OA\Property(property="map_data", type="string", nullable=true, description="Map data JSON"),
 *     @OA\Property(property="map_preview_url", type="string", description="URL to map preview image (defaults to Ninja Kiwi data server)", example="https://data.ninjakiwi.com/btd6/maps/map/TKIEXYSQ/preview"),
 *     @OA\Property(property="map_notes", type="string", nullable=true, description="Additional notes about the map"),
 *     @OA\Property(property="placement_curver", type="integer", nullable=true, description="Placement in current version leaderboard"),
 *     @OA\Property(property="placement_allver", type="integer", nullable=true, description="Placement in all-time leaderboard"),
 *     @OA\Property(property="difficulty", type="integer", nullable=true, description="Map difficulty level"),
 *     @OA\Property(property="optimal_heros", type="array", nullable=true, @OA\Items(type="string"), description="Optimal heroes for this map"),
 *     @OA\Property(property="botb_difficulty", type="integer", nullable=true, description="Brown Border Bloat difficulty"),
 *     @OA\Property(property="remake_of", type="integer", nullable=true, description="ID of the retro map this is a remake of"),
 *     @OA\Property(property="deleted_on", type="string", format="date-time", nullable=true, description="Timestamp when the map was deleted"),
 *     @OA\Property(property="retro_map", ref="#/components/schemas/RetroMap", nullable=true, description="Retro map data (only included when format is Nostalgia Pack or remake_of is not null)"),
 *     @OA\Property(property="is_verified", type="boolean", description="Whether the map has been verified")
 * )
 */
class Map extends Model
{
    use HasFactory, TestableStructure;

    public $timestamps = false;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'code',
        'name',
        'r6_start',
        'map_data',
        'map_preview_url',
        'map_notes',
    ];

    /**
     * Get the map preview URL, with a default to Ninja Kiwi's data server.
     */
    protected function getMapPreviewUrlAttribute(): ?string
    {
        return $this->attributes['map_preview_url'] ?? url("/api/proxy/ninjakiwi/maps/{$this->code}/preview.webp");
    }

    /**
     * Get all completions for this map.
     */
    public function completions(): HasMany
    {
        return $this->hasMany(Completion::class, 'map_code');
    }

    /**
     * Get all creators for this map.
     */
    public function creators(): HasMany
    {
        return $this->hasMany(Creator::class, 'map_code');
    }

    /**
     * Get all verifications for this map.
     */
    public function verifications(): HasMany
    {
        return $this->hasMany(Verification::class, 'map_code');
    }

    /**
     * Get all additional codes for this map.
     */
    public function additionalCodes(): HasMany
    {
        return $this->hasMany(AdditionalCode::class, 'belongs_to', 'code');
    }

    /**
     * Get all aliases for this map.
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(MapAlias::class, 'map_code');
    }

    /**
     * Get all compatibilities for this map.
     */
    public function compatibilities(): HasMany
    {
        return $this->hasMany(MapverCompatibility::class, 'map_code');
    }

    /**
     * Get the default values for the Map JSON structure.
     */
    protected static function defaults(array $overrides = []): array
    {
        $code = $overrides['code'] ?? 'TESTCODE';
        return [
            'code' => $code,
            'name' => 'Test Map',
            'r6_start' => null,
            'map_data' => null,
            'map_preview_url' => "https://data.ninjakiwi.com/btd6/maps/map/{$code}/preview",
            'map_notes' => null,
            'placement_curver' => null,
            'placement_allver' => null,
            'difficulty' => null,
            'optimal_heros' => [],
            'botb_difficulty' => null,
            'remake_of' => null,
            'deleted_on' => null,
            'retro_map' => null,
            'is_verified' => false,
        ];
    }

    /**
     * Get the fields that are allowed when strict mode is enabled.
     */
    protected static function strictFields(): array
    {
        return [
            'code',
            'name',
            'r6_start',
            'map_data',
            'map_preview_url',
            'map_notes',
            'placement_curver',
            'placement_allver',
            'difficulty',
            'optimal_heros',
            'botb_difficulty',
            'remake_of',
            'deleted_on',
            'retro_map',
            'is_verified',
            'aliases',
            'creators',
            'verifications',
        ];
    }
}
