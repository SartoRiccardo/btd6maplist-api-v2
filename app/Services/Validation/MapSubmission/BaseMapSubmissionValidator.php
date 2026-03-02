<?php

namespace App\Services\Validation\MapSubmission;

use App\Models\Format;
use App\Models\MapListMeta;
use App\Models\MapSubmission;
use Illuminate\Support\Facades\Auth;

/**
 * Base validator for map submissions.
 * Performs standard validation checks applicable to all formats.
 */
class BaseMapSubmissionValidator
{
    protected Format $format;
    protected string $mapCode;
    protected int $proposed;

    public function __construct(Format $format, string $mapCode, int $proposed)
    {
        $this->format = $format;
        $this->mapCode = $mapCode;
        $this->proposed = $proposed;
    }

    /**
     * Validate the submission.
     * Throws an exception with a specific message if validation fails.
     * 
     * @return true if validation passes
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validate(): true
    {
        // Permission check: User must have create:map_submission permission
        $user = Auth()->guard('discord')->user();
        $userFormatIds = $user->formatsWithPermission('create:map_submission');
        $hasGlobalPermission = in_array(null, $userFormatIds, true);
        $hasFormatPermission = in_array($this->format->id, $userFormatIds);

        if (!$hasGlobalPermission && !$hasFormatPermission) {
            throw new \Illuminate\Validation\ValidationException(
                validator()->make([], [])->errors()->add('permission', 'You do not have permission to create map submissions for this format.')
            );
        }

        // Status check: Format->map_submission_status must be 'open'
        if ($this->format->map_submission_status !== 'open') {
            throw new \Illuminate\Validation\ValidationException(
                validator()->make([], [])->errors()->add('format_id', 'Map submissions are not currently open for this format.')
            );
        }

        // Pending check: Fail if a pending submission exists for the same map code and format_id
        $pendingSubmission = MapSubmission::where('code', $this->mapCode)
            ->where('format_id', $this->format->id)
            ->whereNull('rejected_by')
            ->whereNull('accepted_meta_id')
            ->first();

        if ($pendingSubmission) {
            throw new \Illuminate\Validation\ValidationException(
                validator()->make([], [])->errors()->add('code', 'A pending submission already exists for this map and format.')
            );
        }

        // Proposed logic: If Format->proposed_difficulties is defined, verify the proposed value exists in that JSON array
        if ($this->format->proposed_difficulties !== null && is_array($this->format->proposed_difficulties)) {
            if (!in_array($this->proposed, $this->format->proposed_difficulties)) {
                throw new \Illuminate\Validation\ValidationException(
                    validator()->make([], [])->errors()->add('proposed', 'The proposed difficulty is not valid for this format.')
                );
            }
        }

        return true;
    }
}
