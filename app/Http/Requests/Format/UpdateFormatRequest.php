<?php

namespace App\Http\Requests\Format;

use App\Constants\FormatConstants;
use App\Http\Requests\BaseRequest;
use App\Models\MapListMeta;
use Carbon\Carbon;

/**
 * @OA\Schema(
 *     schema="UpdateFormatRequest",
 *     required={"name", "hidden", "run_submission_status", "map_submission_status"},
 *     @OA\Property(property="name", type="string", maxLength=255, description="Format name", example="Maplist"),
 *     @OA\Property(property="slug", type="string", maxLength=255, pattern="^[a-z0-9-]+$", description="URL-friendly identifier for the format", example="maplist"),
 *     @OA\Property(property="description", type="string", description="Format description", example="The official maplist format"),
 *     @OA\Property(property="button_text", type="string", maxLength=255, description="Text for submission buttons", example="Submit Run"),
 *     @OA\Property(property="preview_map_1_code", type="string", nullable=true, description="First preview map code (must be valid for this format)", example="TKIEXYSQ"),
 *     @OA\Property(property="preview_map_2_code", type="string", nullable=true, description="Second preview map code (must be valid for this format)", example="TKIEXYSQ"),
 *     @OA\Property(property="preview_map_3_code", type="string", nullable=true, description="Third preview map code (must be valid for this format)", example="TKIEXYSQ"),
 *     @OA\Property(property="map_submission_rules", type="string", description="Rules for map submissions", example="Maps must be verified before submission"),
 *     @OA\Property(property="completion_submission_rules", type="string", description="Rules for completion submissions", example="Video proof required"),
 *     @OA\Property(property="discord_server_url", type="string", nullable=true, description="Discord server invite URL", example="https://discord.gg/..."),
 *     @OA\Property(property="map_submission_wh", type="string", format="uri", nullable=true, description="Discord webhook URL for map submissions", example="https://discord.com/api/webhooks/..."),
 *     @OA\Property(property="run_submission_wh", type="string", format="uri", nullable=true, description="Discord webhook URL for run submissions", example="https://discord.com/api/webhooks/..."),
 *     @OA\Property(property="hidden", type="boolean", description="Whether the format is hidden", example=false),
 *     @OA\Property(property="run_submission_status", type="string", enum={"closed", "open", "lcc_only"}, description="Run submission status", example="open"),
 *     @OA\Property(property="map_submission_status", type="string", enum={"closed", "open", "open_chimps"}, description="Map submission status", example="open_chimps"),
 *     @OA\Property(property="emoji", type="string", maxLength=255, nullable=true, description="Format emoji", example="🎮"),
 *     @OA\Property(
 *         property="proposed_difficulties",
 *         type="array",
 *         nullable=true,
 *         description="List of proposed difficulty names",
 *         @OA\Items(type="string"),
 *         example={"Top 3", "Top 10", "#11 ~ 20"}
 *     )
 * )
 */
class UpdateFormatRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/'],
            'description' => ['nullable', 'string'],
            'button_text' => ['nullable', 'string', 'max:255'],
            'preview_map_1_code' => ['nullable', 'string', 'exists:maps,code'],
            'preview_map_2_code' => ['nullable', 'string', 'exists:maps,code'],
            'preview_map_3_code' => ['nullable', 'string', 'exists:maps,code'],
            'map_submission_rules' => ['nullable', 'string'],
            'completion_submission_rules' => ['nullable', 'string'],
            'discord_server_url' => ['nullable', 'string'],
            'map_submission_wh' => ['nullable', 'url'],
            'run_submission_wh' => ['nullable', 'url'],
            'hidden' => ['required', 'boolean'],
            'run_submission_status' => ['required', 'in:closed,open,lcc_only'],
            'map_submission_status' => ['required', 'in:closed,open,open_chimps'],
            'emoji' => ['nullable', 'string', 'max:255'],
            'proposed_difficulties' => ['nullable', 'array'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $formatId = $this->route('id');
            if (!$formatId) {
                return;
            }

            $maps = [
                'preview_map_1_code' => $this->input('preview_map_1_code'),
                'preview_map_2_code' => $this->input('preview_map_2_code'),
                'preview_map_3_code' => $this->input('preview_map_3_code'),
            ];

            foreach ($maps as $fieldName => $mapId) {
                if ($mapId === null) {
                    continue;
                }

                if (!$this->isMapValidForFormat((int) $mapId, (int) $formatId)) {
                    $validator->errors()->add($fieldName, "The selected map is not valid for this format.");
                }
            }
        });
    }

    /**
     * Check if a map is valid for the given format.
     * Uses the same validation logic as BaseSubmissionValidator.
     */
    protected function isMapValidForFormat(string $mapCode, int $formatId): bool
    {
        $mapMeta = MapListMeta::activeForMap($mapCode, Carbon::now());
        if (!$mapMeta) {
            return false;
        }

        return match ($formatId) {
            FormatConstants::MAPLIST => $mapMeta->placement_curver !== null,
            FormatConstants::MAPLIST_ALL_VERSIONS => $mapMeta->placement_allver !== null,
            FormatConstants::NOSTALGIA_PACK => $mapMeta->remake_of !== null,
            FormatConstants::EXPERT_LIST => $mapMeta->difficulty !== null,
            FormatConstants::BEST_OF_THE_BEST => $mapMeta->botb_difficulty !== null,
            default => true,
        };
    }
}
