<?php

namespace Tests\Feature\Formats;

use App\Constants\FormatConstants;
use App\Models\Format;
use Tests\TestCase;

class FormatNoGeraldoEnabledTest extends TestCase
{
    // Format: is_no_geraldo_enabled flag
    // New boolean column on formats, editable via edit:config permission.

    private array $basePayload = [
        'name' => 'The Maplist',
        'hidden' => false,
        'run_submission_status' => 'open',
        'map_submission_status' => 'open',
    ];

    // GET /formats

    public function test_is_no_geraldo_enabled_appears_in_format_list_response(): void
    {
        $format = $this->getJson('/api/formats')
            ->assertStatus(200)
            ->json('data.0');

        $this->assertArrayHasKey('is_no_geraldo_enabled', $format);
        $this->assertIsBool($format['is_no_geraldo_enabled']);
    }

    public function test_formats_with_flag_true_return_true(): void
    {
        Format::where('id', FormatConstants::MAPLIST)->update(['is_no_geraldo_enabled' => true]);

        $formats = $this->getJson('/api/formats')->assertStatus(200)->json('data');
        $maplist = collect($formats)->firstWhere('id', FormatConstants::MAPLIST);

        $this->assertTrue($maplist['is_no_geraldo_enabled']);
    }

    public function test_formats_with_flag_false_return_false(): void
    {
        Format::where('id', FormatConstants::MAPLIST)->update(['is_no_geraldo_enabled' => false]);

        $formats = $this->getJson('/api/formats')->assertStatus(200)->json('data');
        $maplist = collect($formats)->firstWhere('id', FormatConstants::MAPLIST);

        $this->assertFalse($maplist['is_no_geraldo_enabled']);
    }

    // PUT /formats/{id}

    public function test_user_with_edit_config_permission_can_set_is_no_geraldo_enabled_true(): void
    {
        Format::where('id', FormatConstants::MAPLIST)->update(['is_no_geraldo_enabled' => false]);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:config']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/formats/' . FormatConstants::MAPLIST, array_merge($this->basePayload, [
                'is_no_geraldo_enabled' => true,
            ]))
            ->assertStatus(204);

        $this->assertTrue(Format::find(FormatConstants::MAPLIST)->is_no_geraldo_enabled);
    }

    public function test_user_with_edit_config_permission_can_set_is_no_geraldo_enabled_false(): void
    {
        Format::where('id', FormatConstants::MAPLIST)->update(['is_no_geraldo_enabled' => true]);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:config']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/formats/' . FormatConstants::MAPLIST, array_merge($this->basePayload, [
                'is_no_geraldo_enabled' => false,
            ]))
            ->assertStatus(204);

        $this->assertFalse(Format::find(FormatConstants::MAPLIST)->is_no_geraldo_enabled);
    }

    public function test_no_edit_config_permission_returns_403_field_not_changed(): void
    {
        Format::where('id', FormatConstants::MAPLIST)->update(['is_no_geraldo_enabled' => false]);
        $user = $this->createUserWithPermissions([]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/formats/' . FormatConstants::MAPLIST, array_merge($this->basePayload, [
                'is_no_geraldo_enabled' => true,
            ]))
            ->assertStatus(403);

        $this->assertFalse(Format::find(FormatConstants::MAPLIST)->is_no_geraldo_enabled);
    }

    public function test_non_boolean_value_returns_422(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:config']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/formats/' . FormatConstants::MAPLIST, array_merge($this->basePayload, [
                'is_no_geraldo_enabled' => 'yes',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('is_no_geraldo_enabled');
    }

    public function test_omitting_field_leaves_existing_value_unchanged(): void
    {
        Format::where('id', FormatConstants::MAPLIST)->update(['is_no_geraldo_enabled' => true]);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:config']]);

        // PUT without is_no_geraldo_enabled key
        $this->actingAs($user, 'discord')
            ->putJson('/api/formats/' . FormatConstants::MAPLIST, $this->basePayload)
            ->assertStatus(204);

        $this->assertTrue(Format::find(FormatConstants::MAPLIST)->is_no_geraldo_enabled);
    }
}
