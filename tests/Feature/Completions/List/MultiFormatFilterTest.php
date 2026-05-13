<?php

namespace Tests\Feature\Completions\List;

use App\Constants\FormatConstants;
use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\Map;
use App\Models\User;
use Tests\TestCase;

class MultiFormatFilterTest extends TestCase
{
    // GET /completions?format_id=1,51 — Multi-format Filter
    // format_id now accepts a comma-separated list of format IDs.

    private function createAcceptedMeta(string $mapCode, int $formatId, User $player): CompletionMeta
    {
        $completion = Completion::factory()->create(['map_code' => $mapCode]);
        $meta = CompletionMeta::factory()->create([
            'completion_id' => $completion->id,
            'format_id' => $formatId,
            'accepted_by_id' => '111111111111111111',
            'deleted_on' => null,
            'created_on' => now()->subSeconds(2),
        ]);
        $meta->players()->attach($player->discord_id);
        return $meta;
    }

    public function test_single_format_id_still_works_backward_compatible(): void
    {
        $player = User::factory()->create();
        $map1 = Map::factory()->withMeta(['placement_curver' => 1])->create();
        $map2 = Map::factory()->withMeta(['difficulty' => 1])->create();

        $this->createAcceptedMeta($map1->code, FormatConstants::MAPLIST, $player);
        $this->createAcceptedMeta($map2->code, FormatConstants::EXPERT_LIST, $player);

        $actual = $this->getJson('/api/completions?format_id=' . FormatConstants::MAPLIST)
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(1, $actual);
        $this->assertEquals(FormatConstants::MAPLIST, $actual[0]['format_id']);
    }

    public function test_two_format_ids_returns_completions_from_both_formats(): void
    {
        $player = User::factory()->create();
        $map1 = Map::factory()->withMeta(['placement_curver' => 1])->create();
        $map2 = Map::factory()->withMeta(['difficulty' => 1])->create();

        $this->createAcceptedMeta($map1->code, FormatConstants::MAPLIST, $player);
        $this->createAcceptedMeta($map2->code, FormatConstants::EXPERT_LIST, $player);

        $actual = $this->getJson('/api/completions?format_id=' . FormatConstants::MAPLIST . ',' . FormatConstants::EXPERT_LIST)
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(2, $actual);
        $formatIds = collect($actual)->pluck('format_id')->sort()->values()->toArray();
        $this->assertEquals([FormatConstants::MAPLIST, FormatConstants::EXPERT_LIST], $formatIds);
    }

    public function test_three_or_more_format_ids_all_respected(): void
    {
        $player = User::factory()->create();
        $map1 = Map::factory()->withMeta(['placement_curver' => 1])->create();
        $map2 = Map::factory()->withMeta(['placement_allver' => 1])->create();
        $map3 = Map::factory()->withMeta(['difficulty' => 1])->create();

        $this->createAcceptedMeta($map1->code, FormatConstants::MAPLIST, $player);
        $this->createAcceptedMeta($map2->code, FormatConstants::MAPLIST_ALL_VERSIONS, $player);
        $this->createAcceptedMeta($map3->code, FormatConstants::EXPERT_LIST, $player);

        $ids = implode(',', [FormatConstants::MAPLIST, FormatConstants::MAPLIST_ALL_VERSIONS, FormatConstants::EXPERT_LIST]);
        $actual = $this->getJson('/api/completions?format_id=' . $ids)
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(3, $actual);
    }

    public function test_omitting_format_id_returns_completions_across_all_formats(): void
    {
        $player = User::factory()->create();
        $map1 = Map::factory()->withMeta(['placement_curver' => 1])->create();
        $map2 = Map::factory()->withMeta(['difficulty' => 1])->create();

        $this->createAcceptedMeta($map1->code, FormatConstants::MAPLIST, $player);
        $this->createAcceptedMeta($map2->code, FormatConstants::EXPERT_LIST, $player);

        $actual = $this->getJson('/api/completions')
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(2, $actual);
    }

    public function test_non_existent_format_id_in_list_returns_422(): void
    {
        $this->getJson('/api/completions?format_id=99999')
            ->assertStatus(422);
    }

    public function test_non_numeric_value_in_list_stripped_silently(): void
    {
        $player = User::factory()->create();
        $map1 = Map::factory()->withMeta(['placement_curver' => 1])->create();
        $map2 = Map::factory()->withMeta(['difficulty' => 1])->create();

        $this->createAcceptedMeta($map1->code, FormatConstants::MAPLIST, $player);
        $this->createAcceptedMeta($map2->code, FormatConstants::EXPERT_LIST, $player);

        // 'abc' is stripped; only MAPLIST completions returned
        $actual = $this->getJson('/api/completions?format_id=' . FormatConstants::MAPLIST . ',abc')
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(1, $actual);
        $this->assertEquals(FormatConstants::MAPLIST, $actual[0]['format_id']);
    }

    public function test_all_provided_format_ids_have_no_completions_returns_empty_data(): void
    {
        $actual = $this->getJson('/api/completions?format_id=' . FormatConstants::MAPLIST)
            ->assertStatus(200)
            ->json('data');

        $this->assertEmpty($actual);
    }

    public function test_duplicate_format_ids_in_list_no_duplicated_results(): void
    {
        $player = User::factory()->create();
        $map = Map::factory()->withMeta(['placement_curver' => 1])->create();

        $this->createAcceptedMeta($map->code, FormatConstants::MAPLIST, $player);

        $actual = $this->getJson('/api/completions?format_id=' . FormatConstants::MAPLIST . ',' . FormatConstants::MAPLIST)
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(1, $actual);
    }
}
