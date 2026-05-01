<?php

namespace Tests\Feature\RetroMap;

use Tests\TestCase;

class SearchTest extends TestCase
{
    // GET /retro-maps?search=...
    // Case-insensitive ILIKE search on retro map name.

    public function test_exact_name_match_returns_the_map(): void
    {
        $this->markTestSkipped('Exact name match returns the map');
    }

    public function test_partial_name_match_returns_the_map(): void
    {
        $this->markTestSkipped('Partial name match returns the map — "Meadow" matches "Monkey Meadow"');
    }

    public function test_search_is_case_insensitive(): void
    {
        $this->markTestSkipped('Search is case-insensitive — "monkey meadow" matches "Monkey Meadow"');
    }

    public function test_search_with_no_matches_returns_empty_data_not_an_error(): void
    {
        $this->markTestSkipped('Search with no matches returns empty data, not an error');
    }

    public function test_search_over_255_characters_returns_422(): void
    {
        $this->markTestSkipped('Search over 255 characters returns 422');
    }

    public function test_empty_search_string_returns_all_results(): void
    {
        $this->markTestSkipped('Empty search string — returns all results (not an error, not filtered)');
    }

    public function test_deleted_maps_excluded_from_search_results(): void
    {
        $this->markTestSkipped('Deleted maps excluded from search results');
    }

    public function test_search_combined_with_other_filters_both_applied(): void
    {
        $this->markTestSkipped('Search combined with other filters (e.g. retro_game_id) — both filters applied');
    }
}
