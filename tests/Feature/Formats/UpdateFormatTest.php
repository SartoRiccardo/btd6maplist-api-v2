<?php

namespace Tests\Feature\Formats;

use App\Models\Format;
use App\Models\Map;
use App\Models\MapListMeta;
use Tests\TestCase;
use Tests\Traits\TestsDiscordAuthMiddleware;
use PHPUnit\Metadata\Group;

class UpdateFormatTest extends TestCase
{
    use TestsDiscordAuthMiddleware;

    protected Format $testFormat;

    protected function setUp(): void
    {
        parent::setUp();
        Format::truncate();

        $this->testFormat = Format::factory()->create([
            'id' => 200,
            'name' => 'Original Name',
            'hidden' => false,
            'run_submission_status' => 'closed',
            'map_submission_status' => 'closed',
            'map_submission_wh' => 'https://discord.com/api/webhooks/original-map',
            'run_submission_wh' => 'https://discord.com/api/webhooks/original-run',
            'emoji' => '🎯',
            'proposed_difficulties' => ['Easy', 'Medium'],
        ]);
    }

    protected function endpoint(): string
    {
        return "/api/formats/{$this->testFormat->id}";
    }

    protected function method(): string
    {
        return 'PUT';
    }

    protected function requestData(): array
    {
        return [
            'name' => 'Updated Name',
            'hidden' => true,
            'run_submission_status' => 'open',
            'map_submission_status' => 'open_chimps',
        ];
    }

    protected function expectedSuccessStatusCode(): int
    {
        return 204;
    }

    // -- Custom tests -- //

    #[Group('put')]
    #[Group('formats')]
    public function test_update_format_fails_without_edit_config_permission(): void
    {
        $user = $this->createUserWithPermissions([]);

        $payload = [
            'name' => 'Updated Name',
            'hidden' => true,
            'run_submission_status' => 'open',
            'map_submission_status' => 'open_chimps',
        ];

        $this->actingAs($user, 'discord')
            ->putJson("/api/formats/{$this->testFormat->id}", $payload)
            ->assertStatus(403)
            ->assertJson(['message' => 'Forbidden - Missing edit:config permission for this format.']);
    }

    #[Group('put')]
    #[Group('formats')]
    #[Group('validation')]
    public function test_update_format_with_empty_payload_returns_422_and_check_keys(): void
    {
        $user = $this->createUserWithPermissions([$this->testFormat->id => ['edit:config']]);

        $this->actingAs($user, 'discord')
            ->putJson("/api/formats/{$this->testFormat->id}", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'hidden', 'run_submission_status', 'map_submission_status']);
    }

    #[Group('put')]
    #[Group('formats')]
    public function test_update_format_success_with_proper_permission(): void
    {
        $user = $this->createUserWithPermissions([$this->testFormat->id => ['edit:config']]);

        $payload = [
            'name' => 'Updated Name',
            'hidden' => true,
            'run_submission_status' => 'open',
            'map_submission_status' => 'open_chimps',
        ];

        $this->actingAs($user, 'discord')
            ->putJson("/api/formats/{$this->testFormat->id}", $payload)
            ->assertStatus(204);

        // Verify the update via GET
        $actual = $this->getJson("/api/formats/{$this->testFormat->id}")
            ->assertStatus(200)
            ->json();

        $expected = Format::jsonStructure([
            'id' => $this->testFormat->id,
            ...$payload,
            'proposed_difficulties' => $this->testFormat->proposed_difficulties,
        ]);
        $this->assertEquals($expected, $actual);
    }

    #[Group('put')]
    #[Group('formats')]
    public function test_update_format_successfully_updates_all_fillable_fields(): void
    {
        $user = $this->createUserWithPermissions([$this->testFormat->id => ['edit:config']]);

        $payload = [
            'name' => 'Completely Updated Name',
            'hidden' => true,
            'run_submission_status' => 'lcc_only',
            'map_submission_status' => 'open',
            'map_submission_wh' => 'https://discord.com/api/webhooks/updated-map',
            'run_submission_wh' => 'https://discord.com/api/webhooks/updated-run',
            'emoji' => '🚀',
            'proposed_difficulties' => ['Top 3', 'Top 10', '#11 ~ 20'],
        ];

        $this->actingAs($user, 'discord')
            ->putJson("/api/formats/{$this->testFormat->id}", $payload)
            ->assertStatus(204);

        // Verify the update via GET with include=webhooks
        $actual = $this->actingAs($user, 'discord')
            ->getJson("/api/formats/{$this->testFormat->id}?include=webhooks")
            ->assertStatus(200)
            ->json();

        $expected = Format::jsonStructure([
            'id' => $this->testFormat->id,
            ...$payload,
        ]);
        $this->assertEquals($expected, $actual);
    }

    #[Group('put')]
    #[Group('formats')]
    #[Group('validation')]
    public function test_update_format_validates_status_enum_values(): void
    {
        $user = $this->createUserWithPermissions([$this->testFormat->id => ['edit:config']]);

        // Test invalid run_submission_status
        $this->actingAs($user, 'discord')
            ->putJson("/api/formats/{$this->testFormat->id}", [
                'name' => 'Test',
                'hidden' => false,
                'run_submission_status' => 'invalid_status',
                'map_submission_status' => 'closed',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['run_submission_status']);

        // Test invalid map_submission_status
        $this->actingAs($user, 'discord')
            ->putJson("/api/formats/{$this->testFormat->id}", [
                'name' => 'Test',
                'hidden' => false,
                'run_submission_status' => 'closed',
                'map_submission_status' => 'invalid_status',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['map_submission_status']);
    }

    #[Group('put')]
    #[Group('formats')]
    #[Group('validation')]
    public function test_update_format_validates_webhook_urls(): void
    {
        $user = $this->createUserWithPermissions([$this->testFormat->id => ['edit:config']]);

        $this->actingAs($user, 'discord')
            ->putJson("/api/formats/{$this->testFormat->id}", [
                'name' => 'Test',
                'hidden' => false,
                'run_submission_status' => 'closed',
                'map_submission_status' => 'closed',
                'map_submission_wh' => 'not-a-valid-url',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['map_submission_wh']);
    }

    #[Group('put')]
    #[Group('formats')]
    public function test_update_format_returns_404_if_not_found(): void
    {
        $user = $this->createUserWithPermissions([999 => ['edit:config']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/formats/999', [
                'name' => 'Test',
                'hidden' => false,
                'run_submission_status' => 'closed',
                'map_submission_status' => 'closed',
            ])
            ->assertStatus(404)
            ->assertJson(['message' => 'Not Found']);
    }

    #[Group('put')]
    #[Group('formats')]
    public function test_update_format_with_global_permission(): void
    {
        // User with global edit:config permission (format_id = null)
        $user = $this->createUserWithPermissions([null => ['edit:config']]);

        $payload = [
            'name' => 'Updated via Global Permission',
            'hidden' => true,
            'run_submission_status' => 'open',
            'map_submission_status' => 'open_chimps',
        ];

        $this->actingAs($user, 'discord')
            ->putJson("/api/formats/{$this->testFormat->id}", $payload)
            ->assertStatus(204);

        // Verify the update via GET
        $actual = $this->getJson("/api/formats/{$this->testFormat->id}")
            ->assertStatus(200)
            ->json();

        $expected = Format::jsonStructure([
            'id' => $this->testFormat->id,
            ...$payload,
            'proposed_difficulties' => $this->testFormat->proposed_difficulties,
        ]);
        $this->assertEquals($expected, $actual);
    }

    #[Group('put')]
    #[Group('formats')]
    public function test_update_format_accepts_null_for_nullable_fields(): void
    {
        $user = $this->createUserWithPermissions([$this->testFormat->id => ['edit:config']]);

        $payload = [
            'name' => 'Test Format',
            'hidden' => false,
            'run_submission_status' => 'closed',
            'map_submission_status' => 'closed',
            'map_submission_wh' => null,
            'run_submission_wh' => null,
            'emoji' => null,
            'proposed_difficulties' => null,
        ];

        $this->actingAs($user, 'discord')
            ->putJson("/api/formats/{$this->testFormat->id}", $payload)
            ->assertStatus(204);

        // Verify nullable fields are null via GET with include=webhooks
        $actual = $this->actingAs($user, 'discord')
            ->getJson("/api/formats/{$this->testFormat->id}?include=webhooks")
            ->assertStatus(200)
            ->json();

        $expected = Format::jsonStructure([
            'id' => $this->testFormat->id,
            ...$payload,
        ]);
        $this->assertEquals($expected, $actual);
    }

    // -- Presentation field permission tests -- //

    #[Group('put')]
    #[Group('formats')]
    public function test_user_without_edit_format_presentation_cannot_update_presentation_fields(): void
    {
        $user = $this->createUserWithPermissions([$this->testFormat->id => ['edit:config']]);

        $payload = [
            'name' => 'Updated Name',
            'slug' => 'should-fail',
            'hidden' => false,
            'run_submission_status' => 'closed',
            'map_submission_status' => 'closed',
        ];

        $this->actingAs($user, 'discord')
            ->putJson("/api/formats/{$this->testFormat->id}", $payload)
            ->assertStatus(403)
            ->assertJson(['message' => 'Forbidden - Missing edit:format_presentation permission for this format.']);
    }

    #[Group('put')]
    #[Group('formats')]
    public function test_user_with_edit_format_presentation_can_update_presentation_fields(): void
    {
        $user = $this->createUserWithPermissions([$this->testFormat->id => ['edit:format_presentation', 'edit:config']]);

        $payload = [
            'name' => $this->testFormat->name,  // Same value - not actually updating
            'slug' => 'my-format',
            'description' => 'Test description',
            'button_text' => 'Click Me',
            'hidden' => $this->testFormat->hidden,  // Same value
            'run_submission_status' => $this->testFormat->run_submission_status,
            'map_submission_status' => $this->testFormat->map_submission_status,
        ];

        $this->actingAs($user, 'discord')
            ->putJson("/api/formats/{$this->testFormat->id}", $payload)
            ->assertStatus(204);

        $actual = $this->getJson("/api/formats/{$this->testFormat->id}")
            ->assertStatus(200)
            ->json();

        $this->assertEquals('my-format', $actual['slug']);
        $this->assertEquals('Test description', $actual['description']);
        $this->assertEquals('Click Me', $actual['button_text']);
    }

    #[Group('put')]
    #[Group('formats')]
    public function test_user_with_both_permissions_can_update_all_fields(): void
    {
        $user = $this->createUserWithPermissions([$this->testFormat->id => ['edit:config', 'edit:format_presentation']]);

        $payload = [
            'name' => 'Complete Update',
            'slug' => 'complete',
            'description' => 'Full update',
            'hidden' => true,
            'run_submission_status' => 'open',
            'map_submission_status' => 'open_chimps',
        ];

        $this->actingAs($user, 'discord')
            ->putJson("/api/formats/{$this->testFormat->id}", $payload)
            ->assertStatus(204);

        $actual = $this->getJson("/api/formats/{$this->testFormat->id}")
            ->assertStatus(200)
            ->json();

        $this->assertEquals('Complete Update', $actual['name']);
        $this->assertEquals('complete', $actual['slug']);
        $this->assertEquals('Full update', $actual['description']);
        $this->assertTrue($actual['hidden']);
    }

    // -- Validation tests -- //

    #[Group('put')]
    #[Group('formats')]
    #[Group('validation')]
    public function test_validates_slug_and_preview_maps(): void
    {
        $user = $this->createUserWithPermissions([$this->testFormat->id => ['edit:format_presentation']]);

        // Test invalid slug format
        $this->actingAs($user, 'discord')
            ->putJson("/api/formats/{$this->testFormat->id}", [
                'slug' => 'Invalid Slug!',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);

        // Test preview_map_1_code must exist
        $this->actingAs($user, 'discord')
            ->putJson("/api/formats/{$this->testFormat->id}", [
                'preview_map_1_code' => 'NONEXISTENT',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['preview_map_1_code']);

        // Test preview_map_2_code must exist
        $this->actingAs($user, 'discord')
            ->putJson("/api/formats/{$this->testFormat->id}", [
                'preview_map_2_code' => 'FAKECODE',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['preview_map_2_code']);

        // Test preview_map_3_code must exist
        $this->actingAs($user, 'discord')
            ->putJson("/api/formats/{$this->testFormat->id}", [
                'preview_map_3_code' => 'NOTREAL',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['preview_map_3_code']);
    }

    #[Group('put')]
    #[Group('formats')]
    #[Group('validation')]
    public function test_preview_map_must_be_valid_for_format(): void
    {
        // Use format 1 (MAPLIST) which requires placement_curver
        // First create a map
        Map::factory()->create([
            'code' => 'BADMAP',
        ]);

        // Create MapListMeta WITHOUT placement_curver (invalid for MAPLIST format)
        MapListMeta::factory()->create([
            'code' => 'BADMAP',
            'placement_curver' => null,  // Missing required field for MAPLIST
        ]);

        $user = $this->createUserWithPermissions([1 => ['edit:format_presentation']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/formats/1', [
                'name' => 'Test',
                'slug' => 'test',
                'hidden' => false,
                'run_submission_status' => 'closed',
                'map_submission_status' => 'closed',
                'preview_map_1_code' => 'BADMAP',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['preview_map_1_code']);
    }

    #[Group('put')]
    #[Group('formats')]
    public function test_accepts_null_for_nullable_presentation_fields(): void
    {
        $user = $this->createUserWithPermissions([$this->testFormat->id => ['edit:format_presentation', 'edit:config']]);

        $payload = [
            'name' => 'Nullable Test',
            'slug' => 'nullable-test',
            'preview_map_1_code' => null,
            'preview_map_2_code' => null,
            'preview_map_3_code' => null,
            'discord_server_url' => null,
            'hidden' => false,
            'run_submission_status' => 'closed',
            'map_submission_status' => 'closed',
        ];

        $this->actingAs($user, 'discord')
            ->putJson("/api/formats/{$this->testFormat->id}", $payload)
            ->assertStatus(204);

        $actual = $this->getJson("/api/formats/{$this->testFormat->id}")
            ->assertStatus(200)
            ->json();

        $this->assertEquals('nullable-test', $actual['slug']);
        $this->assertArrayNotHasKey('preview_map_1_code', $actual);
        $this->assertArrayNotHasKey('preview_map_2_code', $actual);
        $this->assertArrayNotHasKey('preview_map_3_code', $actual);
        $this->assertNull($actual['discord_server_url']);
    }
}
