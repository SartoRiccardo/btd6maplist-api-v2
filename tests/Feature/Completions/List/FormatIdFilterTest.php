<?php

namespace Tests\Feature\Completions\List;

use App\Constants\FormatConstants;
use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\Map;
use App\Models\User;
use Tests\Helpers\CompletionTestHelper;
use Tests\TestCase;

class FormatIdFilterTest extends TestCase
{
    #[Group('get')]
    #[Group('completions')]
    #[Group('format_id')]
    public function test_filter_by_non_existent_format_returns_error(): void
    {
        $this->getJson('/api/completions?format_id=999999')
            ->assertStatus(422)
            ->assertJsonValidationErrors('format_id.0');
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('format_id')]
    public function test_filter_by_format_id_returns_only_completions_for_that_format(): void
    {
        $player = User::factory()->create();

        // Create format for testing (Maplist with ID 1)
        $formatId = FormatConstants::MAPLIST;

        // Create included map and completions
        $includedMap = Map::factory()->withMeta()->create();
        $includedCompletions = Completion::factory()
            ->count(3)
            ->sequence(fn($seq) => ['map_code' => $includedMap->code])
            ->create();

        $includedMetas = CompletionMeta::factory()
            ->count(3)
            ->sequence(fn($seq) => [
                'completion_id' => $includedCompletions[$seq->index]->id,
                'created_on' => now()->subSeconds(10 - $seq->index),
                'format_id' => $formatId,  // Link to Maplist format
            ])
            ->create();

        foreach ($includedMetas as $meta) {
            $meta->players()->attach($player, ['run' => $meta->id]);
        }

        // Excluded: completions with different format_id
        $excludedMap = Map::factory()->withMeta()->create();
        $excludedCompletions = Completion::factory()->count(2)
            ->sequence(fn($seq) => ['map_code' => $excludedMap->code])
            ->create();

        $excludedMetas = CompletionMeta::factory()
            ->count(2)
            ->sequence(fn($seq) => [
                'completion_id' => $excludedCompletions[$seq->index]->id,
                'created_on' => now()->subSeconds(20 - $seq->index),
                'format_id' => FormatConstants::EXPERT_LIST,  // Different format
            ])
            ->create();

        foreach ($excludedMetas as $meta) {
            $meta->players()->attach($player, ['run' => $meta->id]);
        }

        $includedMetas->each(fn($meta) => $meta->load(['players', 'completion.map']));
        $expected = CompletionTestHelper::expectedCompletionLists($includedCompletions, $includedMetas);

        $actual = $this->getJson('/api/completions?format_id=' . $formatId)
            ->assertStatus(200)
            ->json();

        $this->assertEquals($expected, $actual);
    }
}
