<?php

namespace Tests\Feature\Formats;

use Tests\TestCase;

class FormatPreviewMapsTest extends TestCase
{
    // GET /formats — Preview Maps
    // Each format now eager-loads three preview maps (preview_map_1_code, preview_map_2_code, preview_map_3_code).

    public function test_format_with_all_three_preview_maps_returns_them_in_response(): void
    {
        $this->markTestSkipped('Format with all three preview maps returns them in response');
    }

    public function test_preview_map_objects_include_the_maps_data(): void
    {
        $this->markTestSkipped('Preview map objects include the map\'s data (code, name, etc.)');
    }

    public function test_format_with_no_preview_maps_returns_null_for_each_preview_map_field(): void
    {
        $this->markTestSkipped('Format with no preview maps set returns null for each preview map field');
    }

    public function test_format_with_only_one_preview_map_others_are_null(): void
    {
        $this->markTestSkipped('Format with only one preview map set — the other two are null, no error');
    }

    public function test_preview_map_code_pointing_to_nonexistent_map_handled_gracefully(): void
    {
        $this->markTestSkipped('Preview map code pointing to a non-existent map — handled gracefully (null, not 500)');
    }
}
