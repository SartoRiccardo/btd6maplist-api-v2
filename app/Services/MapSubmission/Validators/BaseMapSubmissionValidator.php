<?php

namespace App\Services\MapSubmission\Validators;

use App\Constants\FormatConstants;
use App\Models\Format;
use App\Models\MapSubmission;
use App\Models\User;
use App\Models\MapListMeta;
use App\Services\MapSubmission\SubmissionValidatorInterface;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class BaseMapSubmissionValidator implements SubmissionValidatorInterface
{
    protected int $formatId;

    /**
     * Validate map submission data.
     *
     * @param array $data Validated request data
     * @param User $user User submitting the map
     * @return void
     * @throws ValidationException
     */
    public function validate(array $data, User $user): void
    {
        $this->formatId = $data['format_id'];
        $this->validateFormatSubmissionStatus($data['format_id']);
        $this->validateUserPermission($user, $data['format_id']);
        $this->validateNoPendingSubmission($user, $data);
        $this->validateMapNotInList($data['code']);
        $this->validateProposedValue($data['format_id'], $data['proposed']);
    }

    /**
     * Validate that the format accepts map submissions.
     *
     * @param int $formatId
     * @return void
     * @throws ValidationException
     */
    protected function validateFormatSubmissionStatus(int $formatId): void
    {
        $format = Format::findOrFail($formatId);

        if ($format->map_submission_status !== 'open') {
            throw ValidationException::withMessages([
                'format_id' => "Map submissions are closed for this format.",
            ]);
        }
    }

    /**
     * Validate that the user has permission to submit maps to this format.
     *
     * @param User $user
     * @param int $formatId
     * @return void
     * @throws ValidationException
     */
    protected function validateUserPermission(User $user, int $formatId): void
    {
        if (!$user->hasPermission('create:map_submission', $formatId)) {
            throw ValidationException::withMessages([
                'format_id' => "You do not have permission to submit maps for this format.",
            ]);
        }
    }

    /**
     * Validate that there isn't already a pending submission for this map.
     *
     * @param User $user
     * @param array $data
     * @return void
     * @throws ValidationException
     */
    protected function validateNoPendingSubmission(User $user, array $data): void
    {
        $pendingExists = MapSubmission::where('code', $data['code'])
            ->where('format_id', $data['format_id'])
            ->withStatus('pending')
            ->exists();

        if ($pendingExists) {
            throw ValidationException::withMessages([
                'code' => "This map already has a pending submission for this format.",
            ]);
        }
    }

    /**
     * Validate the proposed value against the format's proposed difficulties.
     *
     * @param int $formatId
     * @param mixed $proposed
     * @return void
     * @throws ValidationException
     */
    protected function validateProposedValue(int $formatId, mixed $proposed): void
    {
        $format = Format::findOrFail($formatId);

        // Only validate if format has proposed_difficulties configured
        if ($format->proposed_difficulties !== null) {
            $difficulties = json_decode($format->proposed_difficulties, true);

            if (!is_array($difficulties) || !in_array($proposed, $difficulties, true)) {
                throw ValidationException::withMessages([
                    'proposed' => "The proposed value must be one of: " . implode(', ', $difficulties ?? []),
                ]);
            }
        }
    }

    /**
     * Validate that the map is not already in the list.
     *
     * @param string $mapCode
     * @return void
     * @throws ValidationException
     */
    protected function validateMapNotInList(string $mapCode): void
    {
        $meta = MapListMeta::activeForMap($mapCode, Carbon::now());

        if (!$meta) {
            return;
        }

        $isInList = match ($this->formatId) {
            FormatConstants::EXPERT_LIST => $meta->difficulty !== null,
            FormatConstants::BEST_OF_THE_BEST => $meta->botb_difficulty !== null,
            FormatConstants::NOSTALGIA_PACK => $meta->remake_of !== null,
            default => false,
        };

        if ($isInList) {
            throw ValidationException::withMessages([
                'code' => "This map is already in the list.",
            ]);
        }
    }
}
