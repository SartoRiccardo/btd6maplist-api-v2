<?php

namespace App\Services\CompletionSubmission\Validators;

use App\Constants\FormatConstants;
use App\Models\Format;
use App\Models\MapListMeta;
use App\Models\User;
use App\Services\CompletionSubmission\SubmissionValidatorInterface;
use Illuminate\Validation\ValidationException;

class BaseSubmissionValidator implements SubmissionValidatorInterface
{
    protected ?MapListMeta $mapMeta;

    /**
     * Validate completion submission data.
     *
     * @param array $data Validated request data
     * @param User $user User submitting the completion
     * @return void
     * @throws ValidationException
     */
    public function validate(array $data, User $user): void
    {
        $this->validateFormatSubmissionStatus($data['format_id'], $data['lcc'] ?? null);
        $this->validateUserPermission($user, $data['format_id']);
        $this->validateRecordingRequirement($user, $data, $data['format_id']);
        $this->validateMapForFormat($data['map'], $data['format_id']);
    }

    /**
     * Validate that the format accepts submissions.
     *
     * @param int $formatId
     * @param array|null $lccData
     * @return void
     * @throws ValidationException
     */
    protected function validateFormatSubmissionStatus(int $formatId, ?array $lccData): void
    {
        $format = Format::findOrFail($formatId);

        if ($format->run_submission_status === 'closed') {
            throw ValidationException::withMessages([
                'format_id' => "Submissions are closed for this format.",
            ]);
        }

        if ($format->run_submission_status === 'lcc_only' && $lccData === null) {
            throw ValidationException::withMessages([
                'lcc' => "This format requires Least Cost Chimps data to be provided.",
            ]);
        }
    }

    /**
     * Validate that the user has permission to submit to this format.
     *
     * @param User $user
     * @param int $formatId
     * @return void
     * @throws ValidationException
     */
    protected function validateUserPermission(User $user, int $formatId): void
    {
        if (!$user->hasPermission('create:completion_submission', $formatId)) {
            throw ValidationException::withMessages([
                'format_id' => "You do not have permission to submit completions for this format.",
            ]);
        }
    }

    /**
     * Validate that users with recording requirement provide videos.
     *
     * @param User $user
     * @param array $data
     * @return void
     * @throws ValidationException
     */
    protected function validateRecordingRequirement(User $user, array $data, int $formatId): void
    {
        $hasRecordingRequirement = $user->hasPermission('require:completion_submission:recording', $formatId);

        if ($hasRecordingRequirement && empty($data['proof_videos'])) {
            throw ValidationException::withMessages([
                'proof_videos' => "Video proof is required for your submission.",
            ]);
        }
    }

    /**
     * Validate that the map is valid for the format.
     *
     * @param string $mapCode
     * @param int $formatId
     * @return void
     * @throws ValidationException
     */
    protected function validateMapForFormat(string $mapCode, int $formatId): void
    {
        $this->mapMeta = MapListMeta::activeForMap($mapCode, now());
        if (!$this->mapMeta) {
            throw ValidationException::withMessages([
                'map' => "Map not found.",
            ]);
        }

        // Check that the map has the required metadata for this format
        $isValid = $this->isMapValidForFormat($this->mapMeta, $formatId);

        if (!$isValid) {
            throw ValidationException::withMessages([
                'map' => "This map is not valid for submission to the specified format.",
            ]);
        }
    }

    /**
     * Check if map metadata is valid for the format.
     *
     * @param MapListMeta $meta
     * @param int $formatId
     * @return bool
     */
    protected function isMapValidForFormat(MapListMeta $meta, int $formatId): bool
    {
        return match ($formatId) {
            FormatConstants::MAPLIST => $meta->placement_curver !== null,
            FormatConstants::MAPLIST_ALL_VERSIONS => $meta->placement_allver !== null,
            FormatConstants::NOSTALGIA_PACK => $meta->remake_of !== null,
            FormatConstants::EXPERT_LIST => $meta->difficulty !== null,
            FormatConstants::BEST_OF_THE_BEST => $meta->botb_difficulty !== null,
            default => true,
        };
    }
}
