<?php

namespace App\Http\Requests\Format;

use App\Http\Requests\BaseRequest;

/**
 * @OA\Schema(
 *     schema="UpdateFormatRequest",
 *     required={"name", "hidden", "run_submission_status", "map_submission_status"},
 *     @OA\Property(property="name", type="string", maxLength=255, description="Format name", example="Maplist"),
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
            'map_submission_wh' => ['nullable', 'url'],
            'run_submission_wh' => ['nullable', 'url'],
            'hidden' => ['required', 'boolean'],
            'run_submission_status' => ['required', 'in:closed,open,lcc_only'],
            'map_submission_status' => ['required', 'in:closed,open,open_chimps'],
            'emoji' => ['nullable', 'string', 'max:255'],
            'proposed_difficulties' => ['nullable', 'array'],
        ];
    }
}
