<?php

namespace Database\Seeders\Core;

use App\Constants\FormatConstants;
use App\Models\Format;
use App\Models\Map;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FormatSeeder extends Seeder
{
    use WithoutModelEvents;

    private static array $formats = [
        FormatConstants::MAPLIST => [
            'name' => 'The Maplist',
            'slug' => 'maplist',
            'description' => "This is where you go to suffer — but in a fun way. The community curates this list of the 50 hardest custom maps, ranked from hardest to easiest. Beat them to earn points, climb the leaderboard, and flex on your friends. New maps rotate in to keep the pain fresh.",
            'button_text' => 'Check out the Maplist',
            'preview_map_1_code' => 'ZFKTKEH',
            'preview_map_2_code' => 'ZFFGKFH',
            'preview_map_3_code' => 'ZFFEETW',
            'hidden' => false,
            'run_submission_status' => 'open',
            'map_submission_status' => 'open_chimps',
            'proposed_difficulties' => ["Top 3", "Top 10", "#11 ~ 20", "#21 ~ 30", "#31 ~ 40", "#41 ~ 50"],
        ],
        FormatConstants::MAPLIST_ALL_VERSIONS => [
            'name' => 'Maplist (all versions)',
            'hidden' => true,
            'run_submission_status' => 'closed',
            'map_submission_status' => 'closed',
            'proposed_difficulties' => ["Top 3", "Top 10", "#11 ~ 20", "#21 ~ 30", "#31 ~ 40", "#41 ~ 50"],
        ],
        FormatConstants::EXPERT_LIST => [
            'name' => 'The Expert List',
            'slug' => 'expert-list',
            'description' => "This collection features thoughtfully designed maps where gameplay and decoration matters more than raw difficulty. You can attempt some of the classic challenges in these maps, such as 2 Million Pops CHIMPS or 2 Towers CHIMPS. It's the perfect middle ground - challenging enough to feel rewarding, but fair enough to stay fun.",
            'button_text' => 'Check out the Expert List',
            'preview_map_1_code' => 'ZFFBGCC',
            'preview_map_2_code' => 'ZMOFEYB',
            'preview_map_3_code' => 'ZFFTBHX',
            'hidden' => false,
            'run_submission_status' => 'open',
            'map_submission_status' => 'open_chimps',
            'proposed_difficulties' => ["Casual Expert", "Casual/Medium Expert", "Medium Expert", "Medium/High Expert", "High Expert", "High/True Expert", "True Expert", "True/Extreme Expert", "Extreme Expert"],
        ],
        FormatConstants::BEST_OF_THE_BEST => [
            'name' => 'Best of the Best',
            'slug' => 'best-of-the-best',
            'description' => "Some custom maps are so good they feel like NK made them. This pack is all about gorgeous visuals and high-quality gameplay — no jank, just pure eye candy. Perfect when you want a challenge that also looks amazing. Be careful, it doesn't mean every map here is easy!",
            'button_text' => 'Browse the Best of the Best',
            'preview_map_1_code' => 'ZFKOPFD',
            'preview_map_2_code' => 'ZFFBOHD',
            'preview_map_3_code' => 'ZFFCUCU',
            'hidden' => false,
            'run_submission_status' => 'lcc_only',
            'map_submission_status' => 'open',
            'proposed_difficulties' => ["Beginner", "Intermediate", "Advanced", "Expert/Extreme"],
        ],
        FormatConstants::NOSTALGIA_PACK => [
            'name' => 'Nostalgia Pack',
            'slug' => 'nostalgia-pack',
            'description' => "Miss the old days? These are classic maps from BTD5 and BMC, but rebuilt for BTD6. You can finally bring havoc into these old maps by getting heros and paragons. Time to relive the glory days (and finally beat that one map that haunted you).",
            'button_text' => 'Revisit the Nostalgia Pack',
            'preview_map_1_code' => 'ZFFECXW',
            'preview_map_2_code' => 'ZFMXPDS',
            'preview_map_3_code' => 'ZFFPHXD',
            'hidden' => false,
            'run_submission_status' => 'lcc_only',
            'map_submission_status' => 'open',
            'proposed_difficulties' => null,
        ],
    ];

    private static array $previewMaps = [
        'ZFKTKEH', 'ZFFGKFH', 'ZFFEETW',
        'ZFFBGCC', 'ZMOFEYB', 'ZFFTBHX',
        'ZFKOPFD', 'ZFFBOHD', 'ZFFCUCU',
        'ZFFECXW', 'ZFMXPDS', 'ZFFPHXD',
    ];

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
