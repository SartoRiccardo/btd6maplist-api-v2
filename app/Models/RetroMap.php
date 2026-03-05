<?php

namespace App\Models;

use App\Traits\TestableStructure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @OA\Schema(
 *     schema="RetroMap",
 *     type="object",
 *     @OA\Property(property="id", type="integer", description="Retro map ID", example=82),
 *     @OA\Property(property="name", type="string", description="Retro map name", example="doloremque architecto molestias"),
 *     @OA\Property(property="sort_order", type="integer", description="Sort order", example=82),
 *     @OA\Property(property="preview_url", type="string", description="Preview image URL", example="http://www.little.info/"),
 *     @OA\Property(property="retro_game_id", type="integer", description="ID of the retro game", example=1),
 *     @OA\Property(property="game", ref="#/components/schemas/RetroGame")
 * )
 */
class RetroMap extends Model
{
    use HasFactory, SoftDeletes, TestableStructure;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'name',
        'sort_order',
        'preview_url',
        'retro_game_id',
        'deleted_at',
    ];

    /**
     * Get the retro game this map belongs to.
     */
    public function game()
    {
        return $this->belongsTo(RetroGame::class, 'retro_game_id');
    }

    // --- TestableStructure --- //

    /**
     * Get the default values for the RetroMap JSON structure.
     */
    protected static function defaults(array $overrides = []): array
    {
        return [
            'id' => $overrides['id'] ?? 1,
            'name' => $overrides['name'] ?? 'Test Retro Map',
            'sort_order' => $overrides['sort_order'] ?? 1,
            'preview_url' => $overrides['preview_url'] ?? 'https://example.com/preview.png',
            'retro_game_id' => $overrides['retro_game_id'] ?? 1,
            'game' => [],
        ];
    }

    /**
     * Get the fields that are allowed when strict mode is enabled.
     */
    protected static function strictFields(): array
    {
        return [
            'deleted_at',
            'id',
            'name',
            'sort_order',
            'preview_url',
            'retro_game_id',
            'game',
        ];
    }
}
