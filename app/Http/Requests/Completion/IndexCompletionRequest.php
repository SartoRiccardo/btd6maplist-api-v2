<?php

namespace App\Http\Requests\Completion;

use App\Http\Requests\BaseRequest;

/**
 * @OA\Schema(
 *     schema="IndexCompletionRequest",
 *     @OA\Property(property="timestamp", type="integer", description="Unix timestamp to filter completions active at this time", example=1736123456),
 *     @OA\Property(property="format_id", type="string", description="Comma-separated format IDs to filter by", example="1,51"),
 *     @OA\Property(property="page", type="integer", description="Page number", example=1, minimum=1),
 *     @OA\Property(property="per_page", type="integer", description="Items per page", example=100, minimum=1, maximum=150),
 *     @OA\Property(property="player_id", type="integer", description="Filter by player's discord_id", example=2000000),
 *     @OA\Property(property="map_code", type="string", description="Filter by map code", example="TKIEXYSQ"),
 *     @OA\Property(property="deleted", type="string", enum={"only", "exclude", "any"}, description="Filter by deletion status", example="exclude"),
 *     @OA\Property(property="pending", type="string", enum={"only", "exclude", "any"}, description="Filter pending (not accepted) completions", example="exclude"),
 *     @OA\Property(property="no_geraldo", type="string", enum={"only", "exclude", "any"}, description="Filter by no_geraldo status", example="any"),
 *     @OA\Property(property="lcc", type="string", enum={"only", "exclude", "any"}, description="Filter by LCC presence", example="any"),
 *     @OA\Property(property="black_border", type="string", enum={"only", "exclude", "any"}, description="Filter by black_border status", example="any"),
 *     @OA\Property(property="sort_by", type="string", enum={"created_on"}, description="Field to sort by", example="created_on"),
 *     @OA\Property(property="sort_order", type="string", enum={"asc", "desc"}, description="Sort order", example="asc"),
 *     @OA\Property(property="include", type="string", description="Include additional resources (comma-separated)", example="map.metadata")
 * )
 */
class IndexCompletionRequest extends BaseRequest
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

        $formatId = $this->input('format_id');
        if (is_string($formatId)) {
            $formatId = array_map('intval', array_filter(explode(',', $formatId), fn($v) => is_numeric(trim($v))));
        }

        $this->merge([
            'timestamp' => $this->input('timestamp', time()),
            'format_id' => $formatId,
            'page' => $this->input('page', 1),
            'per_page' => $this->input('per_page', 100),
            'deleted' => $this->input('deleted', 'exclude'),
            'pending' => $this->input('pending', 'exclude'),
            'no_geraldo' => $this->input('no_geraldo', 'any'),
            'lcc' => $this->input('lcc', 'any'),
            'black_border' => $this->input('black_border', 'any'),
            'sort_by' => $this->input('sort_by', 'created_on'),
            'sort_order' => $this->input('sort_order', 'asc'),
            'include' => $include ?? [],
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'timestamp' => ['nullable', 'integer', 'min:0'],
            'format_id' => ['nullable', 'array'],
            'format_id.*' => ['integer', 'min:1', 'exists:formats,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:150'],
            'player_id' => ['nullable', 'integer', 'min:1', 'exists:users,discord_id'],
            'map_code' => ['nullable', 'string', 'exists:maps,code'],
            'deleted' => ['nullable', 'in:only,exclude,any'],
            'pending' => ['nullable', 'in:only,exclude,any'],
            'no_geraldo' => ['nullable', 'in:only,exclude,any'],
            'lcc' => ['nullable', 'in:only,exclude,any'],
            'black_border' => ['nullable', 'in:only,exclude,any'],
            'sort_by' => ['nullable', 'in:created_on'],
            'sort_order' => ['nullable', 'in:asc,desc'],
            'include' => ['nullable', 'array'],
            'include.*' => ['string', 'in:map.metadata,players.flair,admin_note'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'deleted.in' => 'The deleted field must be one of: only, exclude, any.',
            'pending.in' => 'The pending field must be one of: only, exclude, any.',
            'no_geraldo.in' => 'The no_geraldo field must be one of: only, exclude, any.',
            'lcc.in' => 'The lcc field must be one of: only, exclude, any.',
            'black_border.in' => 'The black_border field must be one of: only, exclude, any.',
            'sort_by.in' => 'The sort_by field must be one of: created_on.',
            'sort_order.in' => 'The sort_order field must be one of: asc, desc.',
            'format_id.exists' => 'The selected format does not exist.',
        ];
    }
}
