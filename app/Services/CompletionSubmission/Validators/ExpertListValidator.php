<?php

namespace App\Services\CompletionSubmission\Validators;

use App\Models\MapListMeta;
use App\Models\User;
use App\Services\CompletionSubmission\SubmissionValidatorInterface;
use Illuminate\Validation\ValidationException;

/**
 * Validator for Expert List (format 51).
 * Validates video requirements based on difficulty.
 */
class ExpertListValidator extends BaseSubmissionValidator
{
    /**
     * Validate completion submission data for Expert List format.
     *
     * @param array $data Validated request data
     * @param User $user User submitting the completion
     * @return void
     * @throws ValidationException
     */
    public function validate(array $data, User $user): void
    {
        // Run base validation first
        parent::validate($data, $user);

        $this->validateVideoProofRequirements($data);
    }

    /**
     * Validate that videos are provided when required.
     * - Videos always required if black_border=true or LCC provided.
     * - Videos required for no_geraldo=true ONLY on difficulty 3 or 4 (not 0, 1, 2).
     *
     * @param array $data
     * @return void
     * @throws ValidationException
     */
    protected function validateVideoProofRequirements(array $data): void
    {
        $blackBorder = $data['black_border'] ?? false;
        $noGeraldo = $data['no_geraldo'] ?? false;
        $hasLcc = isset($data['lcc']) && is_array($data['lcc']);

        // Determine if video is required
        $requiresVideo = $blackBorder || $hasLcc;

        // For no_geraldo, video is only required on difficulty 3 or 4
        if ($noGeraldo && in_array($this->mapMeta->difficulty, [3, 4])) {
            $requiresVideo = true;
        }

        if ($requiresVideo && empty($data['proof_videos'])) {
            throw ValidationException::withMessages([
                'proof_videos' => "Video proof is required for this submission.",
            ]);
        }
    }
}
