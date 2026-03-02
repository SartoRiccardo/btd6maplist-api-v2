<?php

namespace App\Services\Validation\MapSubmission;

use App\Constants\FormatConstants;
use App\Models\Format;

/**
 * Factory for creating format-specific map submission validators.
 */
class MapSubmissionValidatorFactory
{
    /**
     * Create a validator instance based on the format_id.
     *
     * @param Format $format The format to validate against
     * @param string $mapCode The map code being submitted
     * @param int $proposed The proposed difficulty/retro map ID
     * @return BaseMapSubmissionValidator
     */
    public static function make(Format $format, string $mapCode, int $proposed): BaseMapSubmissionValidator
    {
        return match ($format->id) {
            FormatConstants::MAPLIST,
            FormatConstants::MAPLIST_ALL_VERSIONS => new MaplistSubmissionValidator($format, $mapCode, $proposed),
            FormatConstants::NOSTALGIA_PACK => new NostalgiaSubmissionValidator($format, $mapCode, $proposed),
            default => new BaseMapSubmissionValidator($format, $mapCode, $proposed),
        };
    }
}
