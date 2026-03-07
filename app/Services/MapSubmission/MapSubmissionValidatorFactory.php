<?php

namespace App\Services\MapSubmission;

use App\Constants\FormatConstants;
use App\Services\MapSubmission\Validators\BaseMapSubmissionValidator;
use App\Services\MapSubmission\Validators\MaplistSubmissionValidator;
use App\Services\MapSubmission\Validators\NostalgiaSubmissionValidator;

class MapSubmissionValidatorFactory
{
    /**
     * Get the appropriate validator for the given format.
     *
     * @param int $formatId
     * @return SubmissionValidatorInterface
     */
    public function getValidator(int $formatId): SubmissionValidatorInterface
    {
        return match ($formatId) {
            FormatConstants::MAPLIST,
            FormatConstants::MAPLIST_ALL_VERSIONS => new MaplistSubmissionValidator(),

            FormatConstants::NOSTALGIA_PACK => new NostalgiaSubmissionValidator(),

            default => new BaseMapSubmissionValidator(),
        };
    }
}
