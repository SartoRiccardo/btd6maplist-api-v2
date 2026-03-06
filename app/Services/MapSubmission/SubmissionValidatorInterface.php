<?php

namespace App\Services\MapSubmission;

use App\Models\User;

interface SubmissionValidatorInterface
{
    /**
     * Validate map submission data.
     *
     * @param array $data Validated request data
     * @param User $user User submitting the map
     * @return void
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validate(array $data, User $user): void;
}
