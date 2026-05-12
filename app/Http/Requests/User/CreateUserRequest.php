<?php

namespace App\Http\Requests\User;

use App\Http\Requests\BaseRequest;
use App\Models\User;
use Illuminate\Validation\Validator;

/**
 * @OA\Schema(
 *     schema="CreateUserRequest",
 *     required={"discord_id", "name"},
 *     @OA\Property(property="discord_id", type="string", description="User's Discord ID", example="123456789012345678"),
 *     @OA\Property(property="name", type="string", maxLength=50, description="Display name (must be unique, case-insensitive)", example="JohnDoe")
 * )
 */
class CreateUserRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'discord_id' => ['required', 'string', 'regex:/^\d+$/'],
            'name' => ['required', 'string', 'max:50'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $data = $validator->getData();

            if (isset($data['discord_id']) && is_numeric($data['discord_id']) && User::find($data['discord_id'])) {
                $validator->errors()->add('discord_id', 'A user with this Discord ID already exists.');
            }

            if (isset($data['name']) && User::whereRaw('LOWER(name) = LOWER(?)', [$data['name']])->exists()) {
                $validator->errors()->add('name', 'The name has already been taken.');
            }
        });
    }
}
