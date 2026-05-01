<?php

namespace Tests\Feature\Users;

use Tests\TestCase;

class ListUsersTest extends TestCase
{
    // GET /users — List Users
    // Returns a paginated, optionally searched list of users. Requires global list:users permission.

    public function test_returns_paginated_user_list_with_200(): void
    {
        $this->markTestSkipped('Returns paginated user list — authenticated user with permission gets 200 with data, total, page, per_page fields');
    }

    public function test_results_sorted_alphabetically_without_search(): void
    {
        $this->markTestSkipped('Results sorted alphabetically without search — names come back A→Z');
    }

    public function test_search_returns_trigram_similar_names(): void
    {
        $this->markTestSkipped('Search returns trigram-similar names — searching "CyberNinja" returns users with similar names, excludes unrelated ones');
    }

    public function test_include_flair_appends_avatar_url_and_banner_url(): void
    {
        $this->markTestSkipped('include=flair appends avatar_url and banner_url — both fields present on every user in data');
    }

    public function test_unauthenticated_returns_401(): void
    {
        $this->markTestSkipped('Unauthenticated returns 401 — no Bearer token');
    }

    public function test_authenticated_but_no_list_users_permission_returns_403(): void
    {
        $this->markTestSkipped('Authenticated but no list:users permission returns 403');
    }

    public function test_search_with_zero_matches_returns_empty_data_and_total_zero(): void
    {
        $this->markTestSkipped('Search with zero matches returns empty data array and total=0');
    }

    public function test_user_below_similarity_threshold_excluded_from_search_results(): void
    {
        $this->markTestSkipped('User below similarity threshold (< 0.1) excluded from search results');
    }

    public function test_per_page_clamped_to_100_even_if_200_requested(): void
    {
        $this->markTestSkipped('per_page clamped to 100 even if 200 requested — response per_page=100, max 100 items in data');
    }

    public function test_page_2_returns_non_overlapping_results_from_page_1(): void
    {
        $this->markTestSkipped('page=2 returns non-overlapping results from page=1');
    }

    public function test_include_flair_on_user_with_no_nk_oak_returns_null_for_both_urls(): void
    {
        $this->markTestSkipped('include=flair on user with no nk_oak returns null for both urls — not missing key, explicitly null');
    }

    public function test_simil_internal_field_never_leaks_into_response(): void
    {
        $this->markTestSkipped('`simil` internal field never leaks into response — even when search is used');
    }

    public function test_include_with_unknown_value_ignored_no_error(): void
    {
        $this->markTestSkipped('include with unknown value (e.g. include=garbage) ignored, no error');
    }
}
