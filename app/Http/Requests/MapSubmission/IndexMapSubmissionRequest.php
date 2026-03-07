<?php

namespace App\Http\Requests\MapSubmission;

use App\Http\Requests\BaseRequest;

/**
 * @OA\Schema(
 *     schema="IndexMapSubmissionRequest",
 *     @OA\Property(property="page", type="integer", description="Page number", example=1, minimum=1),
 *     @OA\Property(property="per_page", type="integer", description="Items per page", example=100, minimum=1, maximum=150),
 *     @OA\Property(property="format_id", type="integer", description="Filter by format ID", example=1, minimum=1),
 *     @OA\Property(property="submitter_id", type="string", description="Filter by submitter's discord_id", example="123456789012345678"),
 *     @OA\Property(property="status", type="string", description="Filter by status", example="pending", enum={"pending", "accepted", "rejected"}),
 *     @OA\Property(property="include", type="string", description="Include additional resources (comma-separated)", example="submitter.flair")
 * )
 */
class IndexMapSubmissionRequest extends BaseRequest
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
            'per_page' => $this->input('per_page', 100),
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
            'per_page' => ['nullable', 'integer', 'min:1', 'max:150'],
            'format_id' => ['nullable', 'integer', 'min:1', 'exists:formats,id'],
            'submitter_id' => ['nullable', 'string', 'max:20', 'exists:users,discord_id'],
            'status' => ['nullable', 'string', 'in:pending,accepted,rejected'],
            'include' => ['nullable', 'array'],
            'include.*' => ['string', 'in:submitter.flair'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'format_id.exists' => 'The selected format does not exist.',
            'submitter_id.exists' => 'The selected submitter does not exist.',
            'status.in' => 'The status must be one of: pending, accepted, rejected.',
            'include.*.in' => 'The include field must be one of: submitter.flair.',
        ];
    }
}
