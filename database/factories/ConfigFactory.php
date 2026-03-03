<?php

namespace Database\Factories;

use App\Models\Config;
use App\Models\ConfigFormat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Config>
 */
class ConfigFactory extends Factory
{
    protected $model = Config::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'value' => fake()->word(),
            'type' => fake()->randomElement(['int', 'float', 'string']),
            'description' => fake()->sentence(),
            'difficulty' => null,
        ];
    }

    /**
     * Set a specific type.
     */
    public function type(string $type): self
    {
        return $this->state(fn(array $attributes) => [
            'type' => $type,
            'value' => match ($type) {
                'int' => (string) fake()->randomNumber(),
                'float' => (string) fake()->randomFloat(2, 0, 1000),
                'string' => fake()->word(),
            },
        ]);
    }

    /**
     * Set a specific name.
     */
    public function name(string $name): self
    {
        return $this->state(fn(array $attributes) => [
            'name' => $name,
        ]);
    }

    /**
     * Create ConfigFormat entries for the given format IDs after creation.
     */
    public function forFormats(array $formatIds): self
    {
        return $this->afterCreating(function (Config $config) use ($formatIds) {
            foreach ($formatIds as $formatId) {
                ConfigFormat::factory()->create([
                    'config_name' => $config->name,
                    'format_id' => $formatId,
                ]);
            }
        });
    }
}
