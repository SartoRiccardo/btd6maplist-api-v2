<?php

namespace Tests\Feature\Maps\List;

use Tests\TestCase;

class FillMissingRetroTest extends TestCase
{
    // GET /maps?fill_missing_retro=true&format_id=11
    // Backfills paginated results with retro maps that have no active remake. Only valid with format_id=11 (Nostalgia Pack).

    public function test_results_include_unmapped_retro_maps_after_regular_maps(): void
    {
        $this->markTestSkipped('Results include unmapped retro maps after the regular maps');
    }

    public function test_retro_maps_with_active_remake_excluded_from_backfill(): void
    {
        $this->markTestSkipped('Retro maps with an active remake are excluded from backfill');
    }

    public function test_format_subfilter_game_id_applied_to_backfill_retro_maps(): void
    {
        $this->markTestSkipped('format_subfilter (game_id) is applied to backfill retro maps too');
    }

    public function test_pagination_meta_accounts_for_backfilled_entries(): void
    {
        $this->markTestSkipped('Pagination meta (total, last_page) accounts for backfilled entries');
    }

    public function test_page_2_offset_correctly_calculated_skips_already_shown_remade_maps(): void
    {
        $this->markTestSkipped('Page 2 offset is correctly calculated (skips already-shown remade maps)');
    }

    public function test_fill_missing_retro_true_without_format_id_11_returns_422(): void
    {
        $this->markTestSkipped('fill_missing_retro=true without format_id=11 returns 422');
    }

    public function test_fill_missing_retro_true_with_format_id_1_returns_422(): void
    {
        $this->markTestSkipped('fill_missing_retro=true with format_id=1 returns 422');
    }

    public function test_fill_missing_retro_false_with_format_id_11_returns_normal_results(): void
    {
        $this->markTestSkipped('fill_missing_retro=false with format_id=11 returns normal results (no backfill)');
    }

    public function test_no_unremade_retro_maps_exist_backfill_adds_nothing(): void
    {
        $this->markTestSkipped('No unremade retro maps exist — backfill adds nothing, response is normal');
    }

    public function test_all_retro_maps_have_remakes_backfill_adds_nothing(): void
    {
        $this->markTestSkipped('All retro maps have remakes — backfill adds nothing');
    }

    public function test_deleted_retro_maps_excluded_from_backfill(): void
    {
        $this->markTestSkipped('Deleted retro maps excluded from backfill');
    }
}
