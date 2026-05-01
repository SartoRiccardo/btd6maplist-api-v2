<?php

namespace Tests\Feature\Completions\List;

use Tests\TestCase;

class PlayersFlairIncludeTest extends TestCase
{
    // GET /completions?include=players.flair
    // Appends avatar_url and banner_url to each player in completion results.

    public function test_player_with_nk_oak_and_cached_flair_returns_avatar_url_and_banner_url(): void
    {
        $this->markTestSkipped('Player with nk_oak and cached flair returns avatar_url and banner_url');
    }

    public function test_multiple_players_in_a_completion_all_get_flair_appended(): void
    {
        $this->markTestSkipped('Multiple players in a completion all get flair appended');
    }

    public function test_player_with_no_nk_oak_returns_null_for_both_urls(): void
    {
        $this->markTestSkipped('Player with no nk_oak returns null for both urls');
    }

    public function test_nk_api_error_for_player_returns_null_urls_rest_of_response_still_returned(): void
    {
        $this->markTestSkipped('NK API error for a player → that player gets null urls, rest of response still returned');
    }

    public function test_include_players_without_flair_does_not_add_flair_fields(): void
    {
        $this->markTestSkipped('include=players (without .flair) does not add flair fields');
    }

    public function test_include_absent_no_flair_fields_on_players(): void
    {
        $this->markTestSkipped('include absent entirely → no flair fields on players');
    }
}
