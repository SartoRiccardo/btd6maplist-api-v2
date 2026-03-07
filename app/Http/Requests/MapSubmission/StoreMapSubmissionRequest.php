<?php

namespace App\Http\Requests\MapSubmission;

use App\Http\Requests\BaseRequest;

/**
 * @OA\Schema(
 *     schema="StoreMapSubmissionRequest",
 *     required={"code", "format_id", "proposed", "completion_proof"},
 *     @OA\Property(property="code", type="string", maxLength=10, description="Map code", example="RiverMoen"),
 *     @OA\Property(property="format_id", type="integer", description="Format ID", example=1, minimum=1),
 *     @OA\Property(property="proposed", type="integer", description="Proposed value (difficulty or retro_map_id)", example=1),
 *     @OA\Property(property="subm_notes", type="string", maxLength=5000, nullable=true, description="Optional submission notes"),
 *     @OA\Property(property="completion_proof", type="string", format="binary", description="Completion proof image file (max 10MB)")
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
            'format_id' => ['required', 'integer', 'min:1', 'exists:formats,id'],
            'proposed' => ['required', 'integer', 'min:0'],
            'subm_notes' => ['nullable', 'string', 'max:5000'],
            'completion_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'format_id.exists' => 'The specified format does not exist.',
            'completion_proof.required' => 'A completion proof image is required.',
            'completion_proof.mimes' => 'The completion proof must be an image file (jpg, jpeg, png, gif, or webp).',
            'completion_proof.max' => 'The completion proof image must not be larger than 10MB.',
        ];
    }
}
