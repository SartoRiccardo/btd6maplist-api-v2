<?php

namespace App\Http\Requests\Map;

use App\Http\Requests\BaseRequest;

/**
 * @OA\Schema(
 *     schema="StoreMapSubmissionRequest",
 *     required={"code", "format_id", "proposed", "completion_proof"},
 *     @OA\Property(property="code", type="string", maxLength=10, description="Map code", example="TKIEXYSQ"),
 *     @OA\Property(property="format_id", type="integer", description="Format ID", example=1),
 *     @OA\Property(property="proposed", type="integer", description="Proposed difficulty (format-specific meaning)", example=25),
 *     @OA\Property(property="subm_notes", type="string", maxLength=5000, nullable=true, description="Optional submission notes"),
 *     @OA\Property(property="completion_proof", type="string", format="binary", description="Proof image file (max 10MB)")
 * )
 */
class StoreMapSubmissionRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:10'],
            'format_id' => ['required', 'integer', 'exists:formats,id'],
            'proposed' => ['required', 'integer'],
            'subm_notes' => ['nullable', 'string', 'max:5000'],
            'completion_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'],
        ];
    }
}
