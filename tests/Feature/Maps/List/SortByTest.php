<?php

namespace Tests\Feature\Maps\List;

use Tests\TestCase;

class SortByTest extends TestCase
{
    // GET /maps?sort_by=...
    // Overrides the default format sort. Valid values: placement_curver, placement_allver, difficulty, botb_difficulty, created_on. NULLs go last.

    public function test_sort_by_placement_curver_returns_maps_in_ascending_order(): void
    {
        $this->markTestSkipped('sort_by=placement_curver returns maps in ascending placement_curver order');
    }

    public function test_sort_by_placement_allver_returns_maps_in_ascending_order(): void
    {
        $this->markTestSkipped('sort_by=placement_allver returns maps in ascending placement_allver order');
    }

    public function test_sort_by_difficulty_returns_maps_in_ascending_order(): void
    {
        $this->markTestSkipped('sort_by=difficulty returns maps in ascending difficulty order');
    }

    public function test_sort_by_botb_difficulty_returns_maps_in_ascending_order(): void
    {
        $this->markTestSkipped('sort_by=botb_difficulty returns maps in ascending botb_difficulty order');
    }

    public function test_sort_by_created_on_returns_maps_oldest_first(): void
    {
        $this->markTestSkipped('sort_by=created_on returns maps oldest-first');
    }

    public function test_secondary_sort_by_created_on_when_primary_values_are_equal(): void
    {
        $this->markTestSkipped('Secondary sort by created_on when primary values are equal — stable tie-breaking');
    }

    public function test_invalid_sort_by_value_returns_422(): void
    {
        $this->markTestSkipped('Invalid sort_by value returns 422');
    }

    public function test_maps_with_null_in_sort_column_appear_last(): void
    {
        $this->markTestSkipped('Maps with NULL in the sort column appear last — non-null values precede them');
    }

    public function test_all_maps_have_null_for_sort_column_all_appear_stable_by_created_on(): void
    {
        $this->markTestSkipped('All maps have NULL for the sort column — they all appear, order stable by created_on');
    }

    public function test_sort_by_overrides_default_format_sort(): void
    {
        $this->markTestSkipped('sort_by overrides the default format sort — with format_id=1 (placement_curver default), using sort_by=difficulty changes the order');
    }
}
