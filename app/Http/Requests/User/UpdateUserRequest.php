<?php

namespace App\Http\Requests\User;

use App\Http\Requests\BaseRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Validation\Validator;

/**
 * @OA\Schema(
 *     schema="UpdateUserRequest",
 *     required={"name"},
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         maxLength=20,
 *         description="Display name (must be unique, case-insensitive)",
 *         example="JohnDoe"
 *     ),
 *     @OA\Property(
 *         property="nk_oak",
 *         type="string",
 *         nullable=true,
 *         description="Ninja Kiwi OpenAPI Key for fetching avatar and banner",
 *         example="abc123def456"
 *     )
 * )
 */
class UpdateUserRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:20'],
            'nk_oak' => ['nullable', 'string'],
        ];
    }

    /**
     * Configure the validator instance.
     * Adds custom validation for NK OAK validity.
     * Note: Name uniqueness is validated in the controller after @me alias resolution.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $data = $validator->getData();

            // NK OAK validation - bypass cache and call API directly
            if (isset($data['nk_oak']) && $data['nk_oak'] !== null && $data['nk_oak'] !== '') {
                /** @var UserService $userService */
                $userService = app(UserService::class);

                if (!$userService->validateOak($data['nk_oak'])) {
                    $validator->errors()->add('nk_oak', 'The provided Ninja Kiwi OAK is invalid.');
                }
            }
        });
    }
}
