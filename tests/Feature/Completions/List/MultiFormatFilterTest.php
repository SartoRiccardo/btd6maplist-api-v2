<?php

namespace Tests\Feature\Completions\List;

use Tests\TestCase;

class MultiFormatFilterTest extends TestCase
{
    // GET /completions?format_id=1,51 — Multi-format Filter
    // format_id now accepts a comma-separated list of format IDs.

    public function test_single_format_id_still_works_backward_compatible(): void
    {
        $this->markTestSkipped('Single format_id still works — backward compatible');
    }

    public function test_two_format_ids_returns_completions_from_both_formats(): void
    {
        $this->markTestSkipped('Two format IDs returns completions from both formats');
    }

    public function test_three_or_more_format_ids_all_respected(): void
    {
        $this->markTestSkipped('Three or more format IDs all respected');
    }

    public function test_omitting_format_id_returns_completions_across_all_formats(): void
    {
        $this->markTestSkipped('Omitting format_id returns completions across all formats');
    }

    public function test_non_existent_format_id_in_list_returns_422(): void
    {
        $this->markTestSkipped('Non-existent format ID in the list returns 422');
    }

    public function test_non_numeric_value_in_list_returns_422(): void
    {
        $this->markTestSkipped('Non-numeric value in the list returns 422 (e.g. "1,abc")');
    }

    public function test_all_provided_format_ids_have_no_completions_returns_empty_data(): void
    {
        $this->markTestSkipped('All provided format IDs have no completions → empty data, not an error');
    }

    public function test_duplicate_format_ids_in_list_no_duplicated_results(): void
    {
        $this->markTestSkipped('Duplicate format IDs in list (e.g. "1,1") — no duplicated results');
    }
}
