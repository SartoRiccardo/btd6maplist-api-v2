<?php

namespace App\Models;

use App\Traits\TestableStructure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @OA\Schema(
 *     schema="RetroGame",
 *     type="object",
 *     @OA\Property(property="id", type="integer", description="Internal ID", example=1),
 *     @OA\Property(property="game_id", type="integer", description="External game ID", example=6048),
 *     @OA\Property(property="category_id", type="integer", description="Category ID", example=888),
 *     @OA\Property(property="subcategory_id", type="integer", description="Subcategory ID", example=923),
 *     @OA\Property(property="game_name", type="string", description="Game name", example="harum sit"),
 *     @OA\Property(property="category_name", type="string", description="Category name", example="dicta"),
 *     @OA\Property(property="subcategory_name", type="string", description="Subcategory name", example="occaecati")
 * )
 */
class RetroGame extends Model
{
    use HasFactory, TestableStructure;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'game_id',
        'category_id',
        'subcategory_id',
        'game_name',
        'category_name',
        'subcategory_name',
    ];

    protected $hidden = [
        //
    ];

    /**
     * Get retro maps for this game.
     */
    public function retroMaps(): HasMany
    {
        return $this->hasMany(RetroMap::class, 'retro_game_id');
    }

    /**
     * Get default values for JSON structure.
     */
    protected static function defaults(array $overrides = []): array
    {
        return [
            'id' => 1,
            'game_id' => 6048,
            'category_id' => 888,
            'subcategory_id' => 923,
            'game_name' => 'Test Game',
            'category_name' => 'Test Category',
            'subcategory_name' => 'Test Subcategory',
        ];
    }

    /**
     * Get fields allowed when strict mode is enabled.
     */
    protected static function strictFields(): array
    {
        return [
            'id',
            'game_id',
            'category_id',
            'subcategory_id',
            'game_name',
            'category_name',
            'subcategory_name',
        ];
    }
}
