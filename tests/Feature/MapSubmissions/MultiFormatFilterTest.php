<?php

namespace Tests\Feature\MapSubmissions;

use Tests\TestCase;

class MultiFormatFilterTest extends TestCase
{
    // GET /map-submissions?format_ids=1,51 — Multi-format Filter
    // Parameter renamed from format_id to format_ids, accepts comma-separated list.

    public function test_single_format_ids_value_works(): void
    {
        $this->markTestSkipped('Single format_ids value works');
    }

    public function test_two_format_ids_returns_submissions_from_both(): void
    {
        $this->markTestSkipped('Two format IDs returns submissions from both');
    }

    public function test_omitting_format_ids_returns_submissions_across_all_formats(): void
    {
        $this->markTestSkipped('Omitting format_ids returns submissions across all formats');
    }

    public function test_old_parameter_name_format_id_is_no_longer_accepted(): void
    {
        $this->markTestSkipped('Old parameter name `format_id` is no longer accepted — no longer filters by it (or returns 422 if validated)');
    }

    public function test_non_existent_format_id_in_list_returns_422(): void
    {
        $this->markTestSkipped('Non-existent format ID in list → 422');
    }

    public function test_non_numeric_value_in_list_returns_422(): void
    {
        $this->markTestSkipped('Non-numeric value in list → 422');
    }

    public function test_all_provided_formats_have_no_submissions_returns_empty_data(): void
    {
        $this->markTestSkipped('All provided formats have no submissions → empty data, not an error');
    }
}
