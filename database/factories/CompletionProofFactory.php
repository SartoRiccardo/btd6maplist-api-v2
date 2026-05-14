<?php

namespace Database\Factories;

use App\Constants\ProofType;
use App\Models\CompletionMeta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CompletionProof>
 */
class CompletionProofFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'run' => CompletionMeta::factory(),
            'proof_url' => fake()->url(),
            'proof_type' => fake()->randomElement([
                ProofType::IMAGE,
                ProofType::VIDEO,
            ]),
            'is_added_by_admin' => false,
        ];
    }

    /**
     * Indicate the proof is an image.
     */
    public function image(): static
    {
        return $this->state(fn(array $attributes) => [
            'proof_type' => ProofType::IMAGE,
            'proof_url' => 'https://dummyimage.com/' . fake()->randomNumber(3, true) . 'x' . fake()->randomNumber(3, true),
        ]);
    }

    /**
     * Indicate the proof was added by an admin.
     */
    public function adminAdded(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_added_by_admin' => true,
        ]);
    }

    /**
     * Indicate the proof is a video.
     */
    public function video(): static
    {
        return $this->state(fn(array $attributes) => [
            'proof_type' => ProofType::VIDEO,
            'proof_url' => 'https://youtu.be/' . fake()->regexify('[a-zA-Z0-9_-]{11}'),
        ]);
    }
}
