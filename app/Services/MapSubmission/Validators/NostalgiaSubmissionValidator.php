<?php

namespace App\Services\MapSubmission\Validators;

use App\Models\RetroMap;
use Illuminate\Validation\ValidationException;

class NostalgiaSubmissionValidator extends BaseMapSubmissionValidator
{
    /**
     * Validate the proposed value is a valid retro map ID.
     *
     * @param int $formatId
     * @param mixed $proposed
     * @return void
     * @throws ValidationException
     */
    protected function validateProposedValue(int $formatId, mixed $proposed): void
    {
        $retroMap = RetroMap::find($proposed);

        if (!$retroMap) {
            throw ValidationException::withMessages([
                'proposed' => "The proposed value must be a valid retro map ID.",
            ]);
        }
    }
}
