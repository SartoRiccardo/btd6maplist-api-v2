<?php

namespace App\Models;

use App\Constants\FormatConstants;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MapListMeta extends Model
{
    use HasFactory;

    protected $table = 'map_list_meta';

    public $timestamps = false;

    protected $fillable = [
        'code',
        'placement_curver',
        'placement_allver',
        'difficulty',
        'optimal_heros',
        'botb_difficulty',
        'remake_of',
        'created_on',
        'deleted_on',
    ];

    protected $hidden = [
        'created_on',
        'id',
    ];

    protected $casts = [
        'optimal_heros' => 'array'
    ];

    /**
     * Get the map for this meta.
     */
    public function map(): BelongsTo
    {
        return $this->belongsTo(Map::class, 'code', 'code');
    }

    /**
     * Get the retro map this meta remakes.
     */
    public function retroMap(): HasOne
    {
        return $this->hasOne(RetroMap::class, 'id', 'remake_of');
    }

    /**
     * Scope to filter by format_id.
     */
    public function scopeForFormat($query, ?int $formatId)
    {
        if (!$formatId) {
            return $query;
        }

        // Get format map count from config
        $mapCount = Config::loadVars(['map_count'])->get('map_count', 50);

        return match ($formatId) {
            FormatConstants::MAPLIST => $query->whereBetween('placement_curver', [1, $mapCount]),
            FormatConstants::MAPLIST_ALL_VERSIONS => $query->whereBetween('placement_allver', [1, $mapCount]),
            FormatConstants::EXPERT_LIST => $query->whereNotNull('difficulty'),
            FormatConstants::BEST_OF_THE_BEST => $query->whereNotNull('botb_difficulty'),
            FormatConstants::NOSTALGIA_PACK => $query->whereNotNull('remake_of'),
            default => $query, // Unknown format, don't filter
        };
    }

    /**
     * Scope to sort based on the given format_id
     */
    public function scopeSortForFormat($query, ?int $formatId)
    {
        $query = match ($formatId) {
            FormatConstants::MAPLIST => $query->orderBy('placement_curver', 'asc'),
            FormatConstants::MAPLIST_ALL_VERSIONS => $query->orderBy('placement_allver', 'asc'),
            FormatConstants::EXPERT_LIST => $query->orderBy('difficulty', 'asc'),
            FormatConstants::BEST_OF_THE_BEST => $query->orderBy('botb_difficulty', 'asc'),
            default => $query, // Unknown format
        };

        return $query->orderBy('created_on', 'asc');
    }

    /**
     * Scope to apply format subfilter.
     * Only applies when format_id is EXPERT_LIST, BEST_OF_THE_BEST, or NOSTALGIA_PACK.
     */
    public function scopeForFormatSubfilter($query, ?int $formatId, ?int $subfilter)
    {
        if (!$formatId || $subfilter === null) {
            return $query;
        }

        return match ($formatId) {
            FormatConstants::EXPERT_LIST => $query->where('difficulty', $subfilter),
            FormatConstants::BEST_OF_THE_BEST => $query->where('botb_difficulty', $subfilter),
            FormatConstants::NOSTALGIA_PACK => $query->whereHas('retroMap.game', function ($q) use ($subfilter) {
                    $q->where('game_id', $subfilter);
                }),
            default => $query, // MAPLIST and MAPLIST_ALL_VERSIONS ignore subfilter
        };
    }

    /**
     * Partial raw query to get the active metas at a timestamp.
     * 
     * @param mixed $timestamp
     */
    public static function activeAtTimestamp($timestamp): \Illuminate\Database\Eloquent\Builder
    {
        return self::selectRaw('DISTINCT ON (code) *')
            ->where('created_on', '<=', $timestamp)
            ->orderBy('code')
            ->orderBy('created_on', 'desc')
            ->orderBy('id', 'desc');
    }

    /**
     * Get the active map meta for a map at a certain timestamp
     * @param string $mapCode
     * @param Carbon $timestamp
     * @return MapListMeta|null
     */
    public static function activeForMap(string $mapCode, Carbon $timestamp): MapListMeta|null
    {
        return self::where('code', $mapCode)
            ->where('created_on', '<=', $timestamp)
            ->orderBy('created_on', 'desc')
            ->first();
    }
}
