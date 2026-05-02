<?php

namespace App\Http\Requests\Completion;

class StoreBotCompletionRequest extends CompletionRequest
{
    protected function prepareForValidation(): void
    {
        $user = auth()->guard('discord')->user();

        $this->merge([
            'map' => $this->input('map'),
            'players' => $user ? [$user->discord_id] : [],
            'accept' => false,
            'lcc' => $this->input('lcc', null),
            'proof_videos' => $this->input('proof_videos', []),
        ]);
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'map' => ['required', 'string', 'max:10', 'exists:maps,code'],
            'subm_notes' => ['nullable', 'string', 'max:5000'],
            'proof_images' => ['required', 'array', 'min:1', 'max:4'],
            'proof_images.*' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'],
            'proof_videos' => ['nullable', 'array', 'max:10'],
            'proof_videos.*' => ['required', 'url', 'max:500'],
        ]);
    }
}
