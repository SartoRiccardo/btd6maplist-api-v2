<?php

namespace Tests\Feature\Formats\List;

use App\Constants\FormatConstants;
use App\Models\Format;
use Tests\Helpers\FormatTestHelper;
use Tests\TestCase;

class ResponseStructureTest extends TestCase
{
    #[Group('get')]
    #[Group('formats')]
    #[Group('response')]
    public function test_format_includes_all_required_fields(): void
    {
        Format::truncate();

        $format = Format::factory()->create([
            'id' => 100,
            'name' => 'Test Format',
            'hidden' => false,
            'run_submission_status' => 'open',
            'map_submission_status' => 'open',
        ]);
        $format = Format::findOrFail($format->id);

        $actual = $this->getJson('/api/formats')
            ->assertStatus(200)
            ->json();

        $expected = FormatTestHelper::expectedFormatList(new \Illuminate\Database\Eloquent\Collection([$format]));

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('formats')]
    #[Group('response')]
    public function test_webhook_columns_excluded_from_response(): void
    {
        Format::truncate();
        Format::factory()->create([
            'map_submission_wh' => 'https://example.com/map-webhook',
            'run_submission_wh' => 'https://example.com/run-webhook',
            'emoji' => '🎮',
        ]);

        $response = $this->getJson('/api/formats')
            ->assertStatus(200)
            ->json();

        $this->assertArrayNotHasKey('map_submission_wh', $response['data'][0]);
        $this->assertArrayNotHasKey('run_submission_wh', $response['data'][0]);
        $this->assertArrayNotHasKey('emoji', $response['data'][0]);
    }
}
