<?php

namespace Database\Seeders\Core;

use App\Constants\FormatConstants;
use App\Models\FormatRuleSubset;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FormatRuleSubsetSeeder extends Seeder
{
    use WithoutModelEvents;

    private static array $rules = [
        [FormatConstants::MAPLIST_ALL_VERSIONS, FormatConstants::MAPLIST],
        [FormatConstants::NOSTALGIA_PACK, FormatConstants::MAPLIST],
        [FormatConstants::NOSTALGIA_PACK, FormatConstants::EXPERT_LIST],
        [FormatConstants::NOSTALGIA_PACK, FormatConstants::BEST_OF_THE_BEST],
        [FormatConstants::EXPERT_LIST, FormatConstants::MAPLIST],
        [FormatConstants::BEST_OF_THE_BEST, FormatConstants::MAPLIST],
        [FormatConstants::BEST_OF_THE_BEST, FormatConstants::EXPERT_LIST],
        [FormatConstants::BEST_OF_THE_BEST, FormatConstants::NOSTALGIA_PACK],
    ];

    public function run(): void
    {
        foreach (self::$rules as [$formatParent, $formatChild]) {
            FormatRuleSubset::firstOrCreate([
                'format_parent' => $formatParent,
                'format_child' => $formatChild,
            ]);
        }
    }
}
