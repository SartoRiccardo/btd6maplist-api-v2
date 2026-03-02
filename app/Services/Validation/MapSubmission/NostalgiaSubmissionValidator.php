<?php

namespace App\Services\Validation\MapSubmission;

use App\Models\RetroMap;
use Illuminate\Validation\ValidationException;

/**
 * Validator for Nostalgia Pack format (ID 11) map submissions.
 * Ensures the proposed value is a valid retro map ID.
 */
class NostalgiaSubmissionValidator extends BaseMapSubmissionValidator
{
    /**
     * Validate the submission for Nostalgia format.
     * 
     * @return true if validation passes
     * @throws ValidationException
     */
    public function validate(): true
    {
        // Call parent validation first
        parent::validate();

        // Proposed logic: The proposed value must be a valid ID from the retro_maps table
        $retroMapExists = RetroMap::where('id', $this->proposed)->exists();

        if (!$retroMapExists) {
            throw new ValidationException(
                validator()->make([], [])->errors()->add('proposed', 'The proposed retro map ID does not exist.')
            );
        }

        return true;
    }
}
