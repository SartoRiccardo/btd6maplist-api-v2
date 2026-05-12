<?php

namespace Database\Seeders\Dev;

use App\Models\Format;
use App\Models\Map;

class FormatSeeder extends \Database\Seeders\Core\FormatSeeder
{
    public function run(): void
    {
        foreach (self::$previewMaps as $code) {
            Map::firstOrCreate(
                ['code' => $code],
                ['name' => $code],
            );
        }

        foreach (self::$formats as $id => $format) {
            Format::updateOrCreate(
                ['id' => $id],
                $format
            );
        }
    }
}
