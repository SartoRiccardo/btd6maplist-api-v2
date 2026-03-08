<?php

namespace Database\Factories;

use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\Map;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Completion>
 */
class CompletionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'map_code' => fn() => Map::factory()->withMeta(),
            'submitted_on' => now(),
            'subm_notes' => null,
            'wh_msg_id' => null,
            'wh_data' => null,
            'copied_from_id' => null,
        ];
    }

    /**
     * Indicate the completion has associated metadata.
     */
    public function withMeta(array $metaAttributes = []): static
    {
        return $this->afterCreating(function (Completion $completion) use ($metaAttributes) {
            CompletionMeta::factory()
                ->for($completion)
                ->create($metaAttributes);
        });
    }
}
