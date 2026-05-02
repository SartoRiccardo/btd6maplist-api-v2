<?php

namespace App\Http\Requests\User;

use App\Http\Requests\BaseRequest;
use App\Services\UserService;
use Illuminate\Validation\Validator;

class UpdateOakRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'nk_oak' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $oak = $validator->getData()['nk_oak'] ?? null;

            if ($oak !== null && $oak !== '') {
                if (!app(UserService::class)->validateOak($oak)) {
                    $validator->errors()->add('nk_oak', 'The provided Ninja Kiwi OAK is invalid.');
                }
            }
        });
    }
}
