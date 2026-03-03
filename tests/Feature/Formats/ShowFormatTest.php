<?php

namespace Tests\Feature\Formats;

use App\Models\Format;
use Tests\TestCase;
use PHPUnit\Metadata\Group;

class ShowFormatTest extends TestCase
{
    #[Group('get')]
    #[Group('formats')]
    #[Group('response')]
    public function test_get_format_returns_standard_fields_without_webhooks(): void
    {
        Format::truncate();

        $format = Format::factory()->create([
            'id' => 100,
            'name' => 'Test Format',
            'hidden' => false,
            'run_submission_status' => 'open',
            'map_submission_status' => 'open_chimps',
            'map_submission_wh' => 'https://discord.com/api/webhooks/map-test',
            'run_submission_wh' => 'https://discord.com/api/webhooks/run-test',
            'emoji' => '🎮',
        ]);

        $actual = $this->getJson("/api/formats/{$format->id}")
            ->assertStatus(200)
            ->json();

        $expected = Format::jsonStructure($format->toArray());
        $this->assertEquals($expected, $actual);

        // Explicitly verify webhook fields are NOT present
        $this->assertArrayNotHasKey('map_submission_wh', $actual);
        $this->assertArrayNotHasKey('run_submission_wh', $actual);
        $this->assertArrayNotHasKey('emoji', $actual);
    }

    #[Group('get')]
    #[Group('formats')]
    #[Group('include')]
    public function test_get_format_with_include_webhooks_returns_all_fields_for_authorized_user(): void
    {
        Format::truncate();

        $format = Format::factory()->create([
            'id' => 101,
            'name' => 'Test Format',
            'hidden' => false,
            'run_submission_status' => 'open',
            'map_submission_status' => 'open_chimps',
            'map_submission_wh' => 'https://discord.com/api/webhooks/map-test',
            'run_submission_wh' => 'https://discord.com/api/webhooks/run-test',
            'emoji' => '🎮',
        ]);

        $user = $this->createUserWithPermissions([$format->id => ['edit:config']]);

        $actual = $this->actingAs($user, 'discord')
            ->getJson("/api/formats/{$format->id}?include=webhooks")
            ->assertStatus(200)
            ->json();

        $expected = $format->toFullArray();
        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('formats')]
    #[Group('include')]
    public function test_get_format_with_include_webhooks_returns_403_for_unauthorized_user(): void
    {
        Format::truncate();

        $format = Format::factory()->create([
            'id' => 102,
            'name' => 'Test Format',
            'hidden' => false,
            'run_submission_status' => 'open',
            'map_submission_status' => 'open_chimps',
            'map_submission_wh' => 'https://discord.com/api/webhooks/map-test',
            'run_submission_wh' => 'https://discord.com/api/webhooks/run-test',
            'emoji' => '🎮',
        ]);

        // User without edit:config permission
        $user = $this->createUserWithPermissions([]);

        $this->actingAs($user, 'discord')
            ->getJson("/api/formats/{$format->id}?include=webhooks")
            ->assertStatus(403)
            ->assertJson(['message' => 'Forbidden - Missing edit:config permission for this format.']);
    }

    #[Group('get')]
    #[Group('formats')]
    #[Group('response')]
    public function test_get_format_returns_404_if_not_found(): void
    {
        $this->getJson('/api/formats/999999')
            ->assertStatus(404)
            ->assertJson(['message' => 'Not Found']);
    }

    #[Group('get')]
    #[Group('formats')]
    #[Group('include')]
    public function test_get_format_with_include_webhooks_returns_401_without_authentication(): void
    {
        Format::truncate();

        $format = Format::factory()->create([
            'id' => 103,
            'name' => 'Test Format',
            'hidden' => false,
        ]);

        // Not authenticated, requesting webhooks
        $this->getJson("/api/formats/{$format->id}?include=webhooks")
            ->assertStatus(401)
            ->assertJson(['message' => 'Unauthorized - Authentication required.']);
    }

    #[Group('get')]
    #[Group('formats')]
    #[Group('include')]
    public function test_get_format_with_global_edit_config_permission_can_see_webhooks(): void
    {
        Format::truncate();

        $format = Format::factory()->create([
            'id' => 104,
            'name' => 'Test Format',
            'map_submission_wh' => 'https://discord.com/api/webhooks/map-test',
            'run_submission_wh' => 'https://discord.com/api/webhooks/run-test',
            'emoji' => '🎮',
        ]);

        // User with global edit:config permission (format_id = null)
        $user = $this->createUserWithPermissions([null => ['edit:config']]);

        $actual = $this->actingAs($user, 'discord')
            ->getJson("/api/formats/{$format->id}?include=webhooks")
            ->assertStatus(200)
            ->json();

        $expected = $format->toFullArray();
        $this->assertEquals($expected, $actual);
    }
}
