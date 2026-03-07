<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;

/**
 * @OA\Schema(
 *     schema="SearchRequest",
 *     required={"q"},
 *     @OA\Property(
 *         property="q",
 *         type="string",
 *         description="Search query. Must contain at least 3 non-space characters.",
 *         example="Infernal"
 *     ),
 *     @OA\Property(
 *         property="entities",
 *         type="string",
 *         description="Comma-separated list of entity types to search. Allowed values: users, maps.",
 *         example="users,maps"
 *     ),
 *     @OA\Property(
 *         property="limit",
 *         type="integer",
 *         description="Maximum number of total results to return.",
 *         example=5,
 *         minimum=1,
 *         maximum=10
 *     )
 * )
 */
class SearchRequest extends BaseRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'entities' => $this->input('entities', 'users,maps'),
            'limit' => $this->input('limit', 5),
        ]);
    }

    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:3'],
            'entities' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:10'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            // Validate q parameter has at least 3 non-space characters
            $q = $this->input('q');
            if ($q !== null) {
                $nonSpaceCount = preg_match_all('/\S/', $q);
                if ($nonSpaceCount < 3) {
                    $validator->errors()->add('q', 'The q field must contain at least 3 non-space characters.');
                }
            }

            // Validate entities parameter
            $entities = $this->input('entities');
            if ($entities !== null) {
                $types = array_map('trim', explode(',', $entities));
                $validTypes = ['users', 'maps'];

                foreach ($types as $type) {
                    if (!in_array($type, $validTypes, true)) {
                        $validator->errors()->add('entities', 'The entities field must be a comma-separated list of: users, maps.');
                        break;
                    }
                }
            }
        });
    }
}
