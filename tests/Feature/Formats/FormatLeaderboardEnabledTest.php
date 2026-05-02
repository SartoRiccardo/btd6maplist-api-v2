<?php

namespace Tests\Feature\Formats;

use Tests\TestCase;

class FormatLeaderboardEnabledTest extends TestCase
{
    // Format: is_lcc_leaderboard_enabled, is_black_border_leaderboard_enabled, is_no_geraldo_leaderboard_enabled flags
    // Three new boolean columns on formats, editable via edit:config permission.

    // GET /formats

    public function test_leaderboard_enabled_flags_appear_in_format_list_response(): void
    {
        $this->markTestSkipped('is_lcc_leaderboard_enabled, is_black_border_leaderboard_enabled, is_no_geraldo_leaderboard_enabled appear in format list response');
    }

    // PATCH /formats/{id}

    public function test_user_with_edit_config_can_set_leaderboard_enabled_flags(): void
    {
        $this->markTestSkipped('User with edit:config can set all three leaderboard enabled flags to false and back to true');
    }

    public function test_non_boolean_leaderboard_flag_value_returns_422(): void
    {
        $this->markTestSkipped('Non-boolean value for any leaderboard enabled flag returns 422');
    }
}
