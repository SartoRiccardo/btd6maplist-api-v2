<?php

namespace App\Http\Requests\Completion;

use Illuminate\Validation\Validator;

/**
 * @OA\Schema(
 *     schema="UpdateCompletionRequest",
 *     @OA\Property(property="format_id", type="integer", description="Format ID", example=1, minimum=1),
 *     @OA\Property(property="black_border", type="boolean", nullable=true, description="Whether the completion achieved black border"),
 *     @OA\Property(property="no_geraldo", type="boolean", nullable=true, description="Whether the completion was done without Geraldo"),
 *     @OA\Property(property="players", type="array", minItems=1, description="List of player Discord IDs who participated in the completion",
 *         @OA\Items(type="string", pattern="^\d{17,20}$", example="123456789012345678")
 *     ),
 *     @OA\Property(property="lcc", type="object", nullable=true, description="Lowest Cost Chimps data",
 *         @OA\Property(property="leftover", type="integer", description="Cash leftover at end of run", minimum=0, example=5000)
 *     ),
 *     @OA\Property(property="accept", type="boolean", description="Whether to accept the completion. Requires edit:completion permission on the format."),
 *     @OA\Property(property="additional_image_proofs", type="array", nullable=true, description="Admin-added image proofs (files or URLs). Replaces all existing admin-added proofs. Omit or send empty array to clear.",
 *         @OA\Items(type="string")
 *     )
 * )
 */
class UpdateCompletionRequest extends CompletionRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $this->merge([
            'additional_image_proofs' => $this->input('additional_image_proofs', []),
        ]);
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'accept' => ['required', 'boolean'],
            'additional_image_proofs' => ['nullable', 'array'],
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        $validator->after(function ($v) {
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxBytes = 10240 * 1024;

            $files = $this->file('additional_image_proofs', []) ?? [];
            $inputs = $this->input('additional_image_proofs', []) ?? [];

            if (count($files) + count($inputs) > 10) {
                $v->errors()->add('additional_image_proofs', 'May not have more than 10 items.');
                return;
            }

            foreach ($files as $i => $file) {
                if (!in_array($file->getMimeType(), $allowedMimes)) {
                    $v->errors()->add("additional_image_proofs.{$i}", 'Must be a valid image (jpg, png, gif, webp).');
                } elseif ($file->getSize() > $maxBytes) {
                    $v->errors()->add("additional_image_proofs.{$i}", 'Image must not exceed 10MB.');
                }
            }

            foreach ($inputs as $i => $url) {
                if (!is_string($url) || !filter_var($url, FILTER_VALIDATE_URL) || strlen($url) > 500) {
                    $v->errors()->add("additional_image_proofs.{$i}", 'Must be a valid URL (max 500 characters).');
                }
            }
        });
    }
}
