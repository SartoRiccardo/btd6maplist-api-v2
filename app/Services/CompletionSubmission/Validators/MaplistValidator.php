<?php

namespace App\Services\CompletionSubmission\Validators;

use App\Constants\FormatConstants;
use App\Models\Config;
use App\Models\MapListMeta;
use App\Models\User;
use App\Services\CompletionSubmission\SubmissionValidatorInterface;
use Illuminate\Validation\ValidationException;

/**
 * Validator for Maplist (format 1) and Maplist All Versions (format 2).
 * Validates placement range and video requirements.
 */
class MaplistValidator extends BaseSubmissionValidator
{
    /**
     * Validate completion submission data for Maplist formats.
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

        $this->validatePlacementField($data);
        $this->validateVideoProofRequirements($data);
    }

    /**
     * Validate that the map's placement is within the valid range (1 to map_count).
     *
     * @param array $data
     * @return void
     * @throws ValidationException
     */
    protected function validatePlacementField(array $data): void
    {
        $mapCount = Config::loadVars(['map_count'])->get('map_count', 50);
        $formatId = $data['format_id'];

        if ($formatId === FormatConstants::MAPLIST) {
            // Maplist - check placement_curver
            if ($this->mapMeta->placement_curver === null || $this->mapMeta->placement_curver < 1 || $this->mapMeta->placement_curver > $mapCount) {
                throw ValidationException::withMessages([
                    'map' => "Map placement is not within the valid range (1-{$mapCount}) for this format.",
                ]);
            }
        } elseif ($formatId === FormatConstants::MAPLIST_ALL_VERSIONS) {
            // Maplist All Versions - check placement_allver
            if ($this->mapMeta->placement_allver === null || $this->mapMeta->placement_allver < 1 || $this->mapMeta->placement_allver > $mapCount) {
                throw ValidationException::withMessages([
                    'map' => "Map placement is not within the valid range (1-{$mapCount}) for this format.",
                ]);
            }
        }
    }

    /**
     * Validate that videos are provided when required.
     * Videos required if: black_border=true, no_geraldo=true, or LCC provided.
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

        $requiresVideo = $blackBorder || $noGeraldo || $hasLcc;

        if ($requiresVideo && empty($data['proof_videos'])) {
            throw ValidationException::withMessages([
                'proof_videos' => "Video proof is required for this submission.",
            ]);
        }
    }
}
