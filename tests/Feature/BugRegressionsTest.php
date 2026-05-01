<?php

namespace Tests\Feature;

use Tests\TestCase;

class BugRegressionsTest extends TestCase
{
    // Achievement Role: for_first validation only runs when for_first=true

    public function test_creating_achievement_role_with_for_first_false_and_another_non_for_first_role_exists_returns_201(): void
    {
        $this->markTestSkipped('Creating achievement role with for_first=false and another non-for_first role exists → 201, no error');
    }

    public function test_creating_achievement_role_with_for_first_true_when_another_for_first_exists_returns_422(): void
    {
        $this->markTestSkipped('Creating achievement role with for_first=true when another for_first already exists → 422');
    }

    public function test_updating_achievement_role_to_for_first_false_when_for_first_role_exists_no_validation_error(): void
    {
        $this->markTestSkipped('Updating an achievement role to for_first=false when for_first role exists → no validation error');
    }

    // Map update: deletes all verifications (not just version-null ones)

    public function test_after_updating_map_version_specific_verifications_are_removed(): void
    {
        $this->markTestSkipped('After updating a map, version-specific verifications are removed');
    }

    public function test_after_updating_map_with_only_version_null_verifications_those_are_also_removed(): void
    {
        $this->markTestSkipped('After updating a map with only version-null verifications, those are also removed');
    }

    public function test_new_verifications_can_be_added_cleanly_after_update_with_no_leftover_old_ones(): void
    {
        $this->markTestSkipped('New verifications can be added cleanly after update with no leftover old ones');
    }

    // Verification list re-indexed after filter

    public function test_get_map_with_filtered_verifications_returns_clean_sequential_json_array(): void
    {
        $this->markTestSkipped('GET /maps/{id} with some verifications filtered out returns a clean sequential JSON array, not an object with gaps');
    }

    // Platform role list shows ALL roles (not just internal)

    public function test_non_internal_roles_appear_in_platform_roles_response(): void
    {
        $this->markTestSkipped('Non-internal roles appear in GET /roles/platform response');
    }

    public function test_internal_roles_appear_in_platform_roles_response(): void
    {
        $this->markTestSkipped('Internal roles also appear in GET /roles/platform response');
    }

    public function test_mix_of_internal_and_non_internal_roles_all_returned(): void
    {
        $this->markTestSkipped('Mix of internal and non-internal roles all returned in GET /roles/platform');
    }

    // all_leaderboards view: no_geraldo called with correct format ID

    public function test_format_1_no_geraldo_leaderboard_returns_completions_for_format_1_not_format_51(): void
    {
        $this->markTestSkipped('Format 1 (Maplist) no_geraldo leaderboard returns completions for format 1, not format 51');
    }

    public function test_format_51_no_geraldo_leaderboard_is_unaffected(): void
    {
        $this->markTestSkipped('Format 51 (Expert List) no_geraldo leaderboard is unaffected');
    }

    public function test_user_no_geraldo_score_in_format_1_matches_actual_format_1_completions(): void
    {
        $this->markTestSkipped("A user's no_geraldo score in format 1 matches their actual format-1 completions");
    }

    // Completion format filter handles empty array correctly

    public function test_omitting_format_id_returns_completions_across_all_formats(): void
    {
        $this->markTestSkipped('Omitting format_id returns completions across all formats (not empty)');
    }

    public function test_providing_format_id_filters_correctly(): void
    {
        $this->markTestSkipped('Providing format_id filters correctly as before');
    }

    // MapService: per-field validation errors for meta_fields

    public function test_meta_fields_validation_failure_returns_per_field_error_messages(): void
    {
        $this->markTestSkipped('When meta_fields validation fails, each involved field gets its own error message');
    }

    public function test_meta_fields_validation_error_message_is_at_least_one_of_these_must_be_set(): void
    {
        $this->markTestSkipped('Error message is "At least one of these must be set" per field, not a single generic message');
    }
}
