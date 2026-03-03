<?php

namespace Database\Factories;

use App\Models\Config;
use App\Models\ConfigFormat;
use App\Models\Format;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ConfigFormat>
 */
class ConfigFormatFactory extends Factory
{
    protected $model = ConfigFormat::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'config_name' => Config::factory(),
            'format_id' => Format::factory(),
        ];
    }
}
