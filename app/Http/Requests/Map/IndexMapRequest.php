<?php

namespace App\Http\Requests\Map;

use App\Http\Requests\BaseRequest;

/**
 * @OA\Schema(
 *     schema="IndexMapRequest",
 *     @OA\Property(property="timestamp", type="integer", description="Unix timestamp to filter maps active at this time", example=1736123456),
 *     @OA\Property(property="format_id", type="integer", description="Format ID filter", example=1),
 *     @OA\Property(property="format_subfilter", type="string", description="Comma-separated format subfilters (difficulty for Expert List, botb_difficulty for BOTB, game_id for Nostalgia Pack)", example="0,2,4"),
 *     @OA\Property(property="page", type="integer", description="Page number", example=1, minimum=1),
 *     @OA\Property(property="per_page", type="integer", description="Items per page", example=100, minimum=1, maximum=500),
 *     @OA\Property(property="deleted", type="string", enum={"only", "exclude", "any"}, description="Filter by deletion status", example="exclude"),
 *     @OA\Property(property="created_by", type="integer", description="Filter by creator's discord_id", example=2000000),
 *     @OA\Property(property="verified_by", type="integer", description="Filter by verifier's discord_id", example=2000000)
 * )
 */
class IndexMapRequest extends BaseRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'timestamp' => $this->input('timestamp', time()),
            'page' => $this->input('page', 1),
            'per_page' => $this->input('per_page', 100),
            'deleted' => $this->input('deleted', 'exclude'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'timestamp' => ['nullable', 'integer', 'min:0'],
            'format_id' => ['nullable', 'integer', 'min:1', 'exists:formats,id'],
            'format_subfilter' => ['nullable', 'integer', 'min:0'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:150'],
            'deleted' => ['nullable', 'in:only,exclude,any'],
            'created_by' => ['nullable', 'integer', 'min:1'],
            'verified_by' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'deleted.in' => 'The deleted field must be one of: only, exclude, any.',
        ];
    }
}
