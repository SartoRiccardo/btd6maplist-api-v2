<?php

namespace App\Http\Requests\RetroGame;

use App\Http\Requests\BaseRequest;

/**
 * @OA\Schema(
 *     schema="IndexRetroGameRequest",
 *     @OA\Property(property="page", type="integer", description="Page number", example=1, minimum=1),
 *     @OA\Property(property="per_page", type="integer", description="Items per page", example=15, minimum=1, maximum=100),
 *     @OA\Property(property="include", type="string", description="Comma-separated includes (progress)", example="progress")
 * )
 */
class IndexRetroGameRequest extends BaseRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $include = $this->input('include');
        if (is_string($include)) {
            $include = array_filter(array_map('trim', explode(',', $include)));
        }

        $this->merge([
            'page' => $this->input('page', 1),
            'per_page' => $this->input('per_page', 15),
            'include' => $include ?? [],
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'include' => ['nullable', 'array'],
            'include.*' => ['string', 'in:progress'],
        ];
    }
}
