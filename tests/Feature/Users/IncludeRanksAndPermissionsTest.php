<?php

namespace Tests\Feature\Users;

use Tests\TestCase;

class IncludeRanksAndPermissionsTest extends TestCase
{
    // GET /users/{id}?include=permissions,ranks

    // include=permissions
    // Returns user's permissions grouped by permission name → array of format IDs (null = global).

    public function test_user_with_global_permission_returns_permission_with_null_in_format_array(): void
    {
        $this->markTestSkipped('User with global permission returns permission with null in format array');
    }

    public function test_user_with_format_scoped_permission_returns_permission_with_format_id_in_array(): void
    {
        $this->markTestSkipped('User with format-scoped permission returns permission with that format_id in array');
    }

    public function test_user_with_same_permission_on_multiple_formats_returns_all_format_ids(): void
    {
        $this->markTestSkipped('User with same permission on multiple formats returns all format_ids in array');
    }

    public function test_multiple_distinct_permissions_returned_as_separate_keys(): void
    {
        $this->markTestSkipped('Multiple distinct permissions returned as separate keys');
    }

    public function test_user_with_no_permissions_returns_empty_object(): void
    {
        $this->markTestSkipped('User with no permissions returns empty object `{}`');
    }

    public function test_permissions_from_multiple_roles_merged_correctly_no_duplicates(): void
    {
        $this->markTestSkipped('Permissions from multiple roles merged correctly (no duplicates)');
    }

    public function test_unknown_include_value_does_not_add_permissions_key(): void
    {
        $this->markTestSkipped('Unknown include value does not add permissions key');
    }

    // include=ranks
    // Returns leaderboard ranks from all_leaderboards view, grouped by format.

    public function test_user_with_completions_has_non_null_placement_for_relevant_lb_type(): void
    {
        $this->markTestSkipped('User with completions has non-null placement for relevant lb_type');
    }

    public function test_response_grouped_by_format_id_with_points_lccs_no_geraldo_black_border_stats(): void
    {
        $this->markTestSkipped('Response grouped by format_id with points/lccs/no_geraldo/black_border stats');
    }

    public function test_user_with_no_leaderboard_entries_returns_empty_ranks_array(): void
    {
        $this->markTestSkipped('User with no leaderboard entries returns empty ranks array `[]`');
    }

    public function test_stats_for_lb_types_user_hasnt_entered_default_to_score_zero_placement_null(): void
    {
        $this->markTestSkipped("Stats for lb_types the user hasn't entered default to score=0, placement=null");
    }
}
