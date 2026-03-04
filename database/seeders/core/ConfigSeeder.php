<?php

namespace Database\Seeders\Core;

use App\Constants\FormatConstants;
use App\Models\Config;
use App\Models\ConfigFormat;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConfigSeeder extends Seeder
{
    use WithoutModelEvents;

    private static array $configs = [
        'points_top_map' => ['value' => '100', 'type' => 'float', 'description' => 'Points for the #1 map'],
        'points_bottom_map' => ['value' => '5', 'type' => 'float', 'description' => 'Points for the last map'],
        'formula_slope' => ['value' => '0.88', 'type' => 'float', 'description' => 'Formula slope'],
        'points_extra_lcc' => ['value' => '20', 'type' => 'float', 'description' => 'Extra points for LCCs'],
        'points_multi_gerry' => ['value' => '2', 'type' => 'float', 'description' => 'No Optimal Hero point multiplier'],
        'points_multi_bb' => ['value' => '3', 'type' => 'float', 'description' => 'Black Border point multiplier'],
        'decimal_digits' => ['value' => '0', 'type' => 'int', 'description' => 'Decimal digits to round to'],
        'map_count' => ['value' => '50', 'type' => 'int', 'description' => 'Number of maps on the list'],
        'current_btd6_ver' => ['value' => '441', 'type' => 'int', 'description' => 'Current BTD6 version'],
        'exp_points_casual' => ['value' => '1', 'type' => 'int', 'difficulty' => 0, 'description' => 'Casual Exp completion points'],
        'exp_points_medium' => ['value' => '2', 'type' => 'int', 'difficulty' => 1, 'description' => 'Medium Exp completion points'],
        'exp_points_high' => ['value' => '3', 'type' => 'int', 'difficulty' => 2, 'description' => 'High Exp completion points'],
        'exp_points_true' => ['value' => '4', 'type' => 'int', 'difficulty' => 3, 'description' => 'True Exp completion points'],
        'exp_points_extreme' => ['value' => '5', 'type' => 'int', 'difficulty' => 4, 'description' => 'Extreme Exp completion points'],
        'exp_nogerry_points_casual' => ['value' => '1', 'type' => 'int', 'difficulty' => 0, 'description' => 'Casual Exp extra'],
        'exp_nogerry_points_medium' => ['value' => '2', 'type' => 'int', 'difficulty' => 1, 'description' => 'Medium Exp extra'],
        'exp_nogerry_points_high' => ['value' => '3', 'type' => 'int', 'difficulty' => 2, 'description' => 'High Exp extra'],
        'exp_nogerry_points_true' => ['value' => '4', 'type' => 'int', 'difficulty' => 3, 'description' => 'True Exp extra'],
        'exp_nogerry_points_extreme' => ['value' => '5', 'type' => 'int', 'difficulty' => 4, 'description' => 'Extreme Exp extra'],
        'exp_bb_multi' => ['value' => '1', 'type' => 'int', 'description' => 'Base points multiplier'],
        'exp_lcc_extra' => ['value' => '0', 'type' => 'int', 'description' => 'Extra points'],
    ];

    private static array $configFormats = [
        FormatConstants::MAPLIST => ['points_top_map', 'points_bottom_map', 'formula_slope', 'points_extra_lcc', 'points_multi_gerry', 'points_multi_bb', 'decimal_digits', 'map_count', 'current_btd6_ver'],
        FormatConstants::MAPLIST_ALL_VERSIONS => ['points_top_map', 'points_bottom_map', 'formula_slope', 'points_extra_lcc', 'points_multi_gerry', 'points_multi_bb', 'decimal_digits', 'map_count', 'current_btd6_ver'],
        FormatConstants::EXPERT_LIST => ['current_btd6_ver', 'exp_points_casual', 'exp_points_medium', 'exp_points_high', 'exp_points_true', 'exp_points_extreme', 'exp_nogerry_points_casual', 'exp_nogerry_points_medium', 'exp_nogerry_points_high', 'exp_nogerry_points_true', 'exp_nogerry_points_extreme', 'exp_bb_multi', 'exp_lcc_extra'],
    ];

    public function run(): void
    {
        // Seed configs
        foreach (self::$configs as $name => $config) {
            Config::updateOrCreate(
                ['name' => $name],
                array_merge(['name' => $name], $config)
            );
        }

        // Seed config formats
        foreach (self::$configFormats as $formatId => $configNames) {
            foreach ($configNames as $configName) {
                ConfigFormat::updateOrCreate(
                    [
                        'config_name' => $configName,
                        'format_id' => $formatId,
                    ]
                );
            }
        }

        DB::statement("REFRESH MATERIALIZED VIEW listmap_points");
        DB::statement("REFRESH MATERIALIZED VIEW snapshot_lb_linked_roles");
    }
}
