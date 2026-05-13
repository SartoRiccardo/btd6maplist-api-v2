<?php

namespace Tests\Feature\Formats;

use App\Constants\FormatConstants;
use App\Models\Format;
use Tests\TestCase;

class FormatLeaderboardEnabledTest extends TestCase
{
    // Format: is_lcc_leaderboard_enabled, is_black_border_leaderboard_enabled, is_no_geraldo_leaderboard_enabled flags
    // Three new boolean columns on formats, editable via edit:config permission.

    // GET /formats

    public function test_leaderboard_enabled_flags_appear_in_format_list_response(): void
    {
        $format = $this->getJson('/api/formats')
            ->assertStatus(200)
            ->json('data.0');

        $this->assertArrayHasKey('is_lcc_leaderboard_enabled', $format);
        $this->assertArrayHasKey('is_black_border_leaderboard_enabled', $format);
        $this->assertArrayHasKey('is_no_geraldo_leaderboard_enabled', $format);
        $this->assertIsBool($format['is_lcc_leaderboard_enabled']);
        $this->assertIsBool($format['is_black_border_leaderboard_enabled']);
        $this->assertIsBool($format['is_no_geraldo_leaderboard_enabled']);
    }

    // PUT /formats/{id}

    public function test_user_with_edit_config_can_set_leaderboard_enabled_flags(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:config']]);
        $base = ['name' => 'The Maplist', 'hidden' => false, 'run_submission_status' => 'open', 'map_submission_status' => 'open'];

        $this->actingAs($user, 'discord')
            ->putJson('/api/formats/' . FormatConstants::MAPLIST, array_merge($base, [
                'is_lcc_leaderboard_enabled' => false,
                'is_black_border_leaderboard_enabled' => false,
                'is_no_geraldo_leaderboard_enabled' => false,
            ]))
            ->assertStatus(204);

        $format = Format::find(FormatConstants::MAPLIST);
        $this->assertFalse($format->is_lcc_leaderboard_enabled);
        $this->assertFalse($format->is_black_border_leaderboard_enabled);
        $this->assertFalse($format->is_no_geraldo_leaderboard_enabled);

        $this->actingAs($user, 'discord')
            ->putJson('/api/formats/' . FormatConstants::MAPLIST, array_merge($base, [
                'is_lcc_leaderboard_enabled' => true,
                'is_black_border_leaderboard_enabled' => true,
                'is_no_geraldo_leaderboard_enabled' => true,
            ]))
            ->assertStatus(204);

        $format->refresh();
        $this->assertTrue($format->is_lcc_leaderboard_enabled);
        $this->assertTrue($format->is_black_border_leaderboard_enabled);
        $this->assertTrue($format->is_no_geraldo_leaderboard_enabled);
    }

    public function test_non_boolean_leaderboard_flag_value_returns_422(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:config']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/formats/' . FormatConstants::MAPLIST, [
                'name' => 'The Maplist',
                'hidden' => false,
                'run_submission_status' => 'open',
                'map_submission_status' => 'open',
                'is_lcc_leaderboard_enabled' => 'not-a-bool',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('is_lcc_leaderboard_enabled');
    }
}
