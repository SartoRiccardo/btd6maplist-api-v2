<?php

namespace Database\Factories;

use App\Models\Format;
use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MapSubmission>
 */
class MapSubmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fn() => Map::factory()->withMeta()->create()->code,
            'submitter_id' => fn() => User::factory()->create()->discord_id,
            'subm_notes' => null,
            'format_id' => Format::first()?->id ?? 1,
            'proposed' => 1,
            'rejected_by' => null,
            'created_on' => now(),
            'completion_proof' => 'map_submission_proofs/test.jpg',
            'accepted_meta_id' => null,
        ];
    }

    /**
     * Indicate the submission is pending (not rejected, not accepted).
     */
    public function pending(): static
    {
        return $this->state(fn(array $attributes) => [
            'rejected_by' => null,
            'accepted_meta_id' => null,
        ]);
    }

    /**
     * Indicate the submission is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn(array $attributes) => [
            'rejected_by' => fn() => User::factory()->create()->discord_id,
            'accepted_meta_id' => null,
        ]);
    }

    /**
     * Indicate the submission is accepted.
     */
    public function accepted(): static
    {
        return $this->state(fn(array $attributes) => [
            'rejected_by' => null,
            'accepted_meta_id' => fn() => MapListMeta::factory()->create()->id,
        ]);
    }
}
