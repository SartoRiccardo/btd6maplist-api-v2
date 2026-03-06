<?php

namespace App\Services\MapSubmission\Validators;

use App\Constants\FormatConstants;
use App\Models\Config;
use App\Models\MapListMeta;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class MaplistSubmissionValidator extends BaseMapSubmissionValidator
{
    /**
     * Validate that the map is not already in the list.
     * Override base validator to allow maps that are dropping off the list.
     *
     * @param string $mapCode
     * @return void
     * @throws ValidationException
     */
    protected function validateMapNotInList(string $mapCode): void
    {
        // Get map count from config
        $mapCount = Config::loadVars(['map_count'])->get('map_count', 50);

        // Get active meta for this map
        $meta = MapListMeta::activeForMap($mapCode, Carbon::now());

        if (!$meta) {
            // No active meta means map is not in the list, which is fine
            return;
        }

        // Check if map is in the list based on format
        $isInList = match ($this->formatId) {
            FormatConstants::MAPLIST => $meta->placement_curver !== null && $meta->placement_curver < $mapCount,
            FormatConstants::MAPLIST_ALL_VERSIONS => $meta->placement_allver !== null && $meta->placement_allver < $mapCount,
            default => false,
        };

        if ($isInList) {
            throw ValidationException::withMessages([
                'code' => "This map is already in the list.",
            ]);
        }
        // If placement >= map_count or null, map is dropping off or not in list - allow submission
    }
}
