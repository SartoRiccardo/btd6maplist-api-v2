<?php

namespace App\Services\CompletionSubmission;

use App\Constants\FormatConstants;
use App\Services\CompletionSubmission\Validators\BaseSubmissionValidator;
use App\Services\CompletionSubmission\Validators\ExpertListValidator;
use App\Services\CompletionSubmission\Validators\MaplistValidator;

class CompletionSubmissionValidatorFactory
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
            FormatConstants::MAPLIST_ALL_VERSIONS => new MaplistValidator(),

            FormatConstants::EXPERT_LIST => new ExpertListValidator(),

            default => new BaseSubmissionValidator(),
        };
    }
}
