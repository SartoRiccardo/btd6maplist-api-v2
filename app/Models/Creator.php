<?php

namespace App\Models;

use App\Traits\TestableStructure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class Creator extends Pivot
{
    use HasFactory, TestableStructure;

    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;

    protected $table = 'creators';

    protected $fillable = [
        'user_id',
        'map_code',
        'role',
    ];

    protected $hidden = [
        'map_code',
    ];

    protected $casts = [
        'user_id' => 'string',
    ];

    /**
     * Get the user who created this map.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'discord_id');
    }

    /**
     * Get the map.
     */
    public function map()
    {
        return $this->belongsTo(Map::class, 'map_code', 'code');
    }

    // --- TestableStructure --- //

    /**
     * Get the default values for the Creator JSON structure.
     */
    protected static function defaults(array $overrides = []): array
    {
        return [
            'user_id' => $overrides['user_id'] ?? '123456789012345678',
            'map_code' => $overrides['map_code'] ?? 'TESTCODE',
            'role' => $overrides['role'] ?? 'Gameplay',
            'user' => [],
        ];
    }

    /**
     * Get the fields that are allowed when strict mode is enabled.
     */
    protected static function strictFields(): array
    {
        return [
            'user_id',
            'role',
            'user',
        ];
    }
}
