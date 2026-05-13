<?php

namespace Tests\Feature\MapSubmissions;

use App\Constants\FormatConstants;
use App\Models\Format;
use App\Models\MapSubmission;
use Tests\TestCase;

class MultiFormatFilterTest extends TestCase
{
    // GET /map-submissions?format_ids=1,51 — Multi-format Filter
    // Parameter renamed from format_id to format_ids, accepts comma-separated list.

    public function test_single_format_ids_value_works(): void
    {
        $format1 = Format::find(FormatConstants::MAPLIST);
        $format2 = Format::find(FormatConstants::EXPERT_LIST);

        MapSubmission::factory()->for($format1)->count(2)->create();
        MapSubmission::factory()->for($format2)->count(3)->create();

        $actual = $this->getJson("/api/maps/submissions?format_ids={$format1->id}")
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(2, $actual);
        foreach ($actual as $s) {
            $this->assertEquals($format1->id, $s['format_id']);
        }
    }

    public function test_two_format_ids_returns_submissions_from_both(): void
    {
        $format1 = Format::find(FormatConstants::MAPLIST);
        $format2 = Format::find(FormatConstants::EXPERT_LIST);

        MapSubmission::factory()->for($format1)->count(2)->create();
        MapSubmission::factory()->for($format2)->count(3)->create();

        $actual = $this->getJson("/api/maps/submissions?format_ids={$format1->id},{$format2->id}")
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(5, $actual);
        $formatIds = collect($actual)->pluck('format_id')->unique()->sort()->values()->toArray();
        $this->assertEquals([$format1->id, $format2->id], $formatIds);
    }

    public function test_omitting_format_ids_returns_submissions_across_all_formats(): void
    {
        $format1 = Format::find(FormatConstants::MAPLIST);
        $format2 = Format::find(FormatConstants::EXPERT_LIST);

        MapSubmission::factory()->for($format1)->count(2)->create();
        MapSubmission::factory()->for($format2)->count(2)->create();

        $actual = $this->getJson('/api/maps/submissions')
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(4, $actual);
    }

    public function test_non_existent_format_id_in_list_returns_422(): void
    {
        $this->getJson('/api/maps/submissions?format_ids=99999')
            ->assertStatus(422);
    }

    public function test_non_numeric_value_in_list_stripped_silently(): void
    {
        $format1 = Format::find(FormatConstants::MAPLIST);
        $format2 = Format::find(FormatConstants::EXPERT_LIST);

        MapSubmission::factory()->for($format1)->count(2)->create();
        MapSubmission::factory()->for($format2)->count(2)->create();

        // Non-numeric 'abc' is stripped; only format1 submissions returned
        $actual = $this->getJson("/api/maps/submissions?format_ids={$format1->id},abc")
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(2, $actual);
        foreach ($actual as $s) {
            $this->assertEquals($format1->id, $s['format_id']);
        }
    }

    public function test_all_provided_formats_have_no_submissions_returns_empty_data(): void
    {
        $format1 = Format::find(FormatConstants::MAPLIST);

        $actual = $this->getJson("/api/maps/submissions?format_ids={$format1->id}")
            ->assertStatus(200)
            ->json('data');

        $this->assertEmpty($actual);
    }
}
