<?php

namespace Tests\Feature\RetroGames;

use Tests\TestCase;

class ProgressIncludeTest extends TestCase
{
    // GET /retro-games?include=progress
    // Adds total_maps and maps_remade to each retro game in the response.

    public function test_game_with_maps_and_some_remade_both_counts_correct(): void
    {
        $this->markTestSkipped('Game with maps and some remade — both counts correct');
    }

    public function test_maps_remade_count_matches_maps_with_active_remake(): void
    {
        $this->markTestSkipped('maps_remade count matches maps that have an active remake (non-deleted meta with remake_of)');
    }

    public function test_total_maps_reflects_all_non_deleted_retro_maps_for_that_game(): void
    {
        $this->markTestSkipped('total_maps reflects all non-deleted retro maps for that game');
    }

    public function test_game_with_zero_retro_maps_returns_zero_not_null_or_missing(): void
    {
        $this->markTestSkipped('Game with zero retro maps → total_maps=0, maps_remade=0 (not missing, not null)');
    }

    public function test_deleted_retro_maps_not_counted_in_total_maps(): void
    {
        $this->markTestSkipped('Deleted retro maps not counted in total_maps');
    }

    public function test_retro_map_with_deleted_remake_does_not_count_toward_maps_remade(): void
    {
        $this->markTestSkipped('Retro map with a deleted remake does not count toward maps_remade — remake must be active');
    }

    public function test_maps_remade_never_exceeds_total_maps(): void
    {
        $this->markTestSkipped('maps_remade never exceeds total_maps');
    }

    public function test_include_absent_total_maps_and_maps_remade_not_present_in_response(): void
    {
        $this->markTestSkipped('include absent → total_maps and maps_remade not present in response');
    }

    public function test_include_progress_with_unknown_second_value_progress_still_works(): void
    {
        $this->markTestSkipped('include=progress with unknown second value (e.g. "progress,garbage") — progress still works');
    }
}
