<?php

namespace App\Services\Validation\MapSubmission;

use App\Constants\FormatConstants;
use App\Models\Config;
use App\Models\MapListMeta;
use Illuminate\Validation\ValidationException;

/**
 * Validator for Maplist format (IDs 1 & 2) map submissions.
 * Ensures the map is not already in the active list.
 */
class MaplistSubmissionValidator extends BaseMapSubmissionValidator
{
    /**
     * Validate the submission for Maplist formats.
     * 
     * @return true if validation passes
     * @throws ValidationException
     */
    public function validate(): true
    {
        // Call parent validation first
        parent::validate();

        // In-List Check: Retrieve map_count from Config
        $mapCount = Config::loadVars(['map_count'])->get('map_count', 50);

        // Check the current MapListMeta for the map code
        $activeMeta = MapListMeta::where('code', $this->mapCode)
            ->whereNull('deleted_on')
            ->first();

        if ($activeMeta) {
            // Determine which placement to check based on format
            $placement = match ($this->format->id) {
                FormatConstants::MAPLIST => $activeMeta->placement_curver,
                FormatConstants::MAPLIST_ALL_VERSIONS => $activeMeta->placement_allver,
                default => null,
            };

            // If placement exists and is less than map_count, map is already in the list
            if ($placement !== null && $placement < $mapCount) {
                throw new ValidationException(
                    validator()->make([], [])->errors()->add('code', 'This map is already in the active list.')
                );
            }
        }

        return true;
    }
}
