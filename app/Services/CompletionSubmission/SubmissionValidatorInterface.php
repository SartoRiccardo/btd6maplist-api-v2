<?php

namespace App\Services\CompletionSubmission;

use App\Models\User;

interface SubmissionValidatorInterface
{
    /**
     * Validate completion submission data.
     *
     * @param array $data Validated request data
     * @param User $user User submitting the completion
     * @return void
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validate(array $data, User $user): void;
}
