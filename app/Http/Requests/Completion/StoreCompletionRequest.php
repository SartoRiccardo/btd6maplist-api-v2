<?php

namespace App\Http\Requests\Completion;

/**
 * @OA\Schema(
 *     schema="StoreCompletionRequest",
 *     required={"map", "proof_images"},
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/CompletionRequest"),
 *         @OA\Schema(
 *             required={"map", "proof_images"},
 *             @OA\Property(property="map", type="string", maxLength=10, description="Map code", example="TKIEXYSQ"),
 *             @OA\Property(property="subm_notes", type="string", maxLength=5000, nullable=true, description="Optional submission notes"),
 *             @OA\Property(property="proof_images", type="array", minItems=1, maxItems=4, description="Image proof files (1-4 required)",
 *                 @OA\Items(type="string", format="binary")
 *             ),
 *             @OA\Property(property="proof_videos", type="array", maxItems=10, description="Video proof URLs (optional)",
 *                 @OA\Items(type="string", format="uri")
 *             )
 *         )
 *     }
 * )
 */
class StoreCompletionRequest extends CompletionRequest
{
    /**
     * Prepare input for validation.
     * Backfill array fields that may be missing in multipart/form-data.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'proof_videos' => $this->input('proof_videos', []),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'map' => ['required', 'string', 'max:10', 'exists:maps,code'],
            'subm_notes' => ['nullable', 'string', 'max:1500', function ($attribute, $value, $fail) {
                if (substr_count($value, "\n") > 40) {
                    $fail('The submission notes may not have more than 40 newlines.');
                }
            }],
            'proof_images' => ['required', 'array', 'min:1', 'max:4'],
            'proof_images.*' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'],
            'proof_videos' => ['nullable', 'array', 'max:10'],
            'proof_videos.*' => ['required', 'url', 'max:500'],
        ]);
    }
}
