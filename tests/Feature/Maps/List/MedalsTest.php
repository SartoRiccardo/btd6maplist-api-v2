<?php

namespace Tests\Feature\Maps\List;

use Tests\TestCase;

class MedalsTest extends TestCase
{
    // GET /maps?include=medals
    // Adds per-map medal flags for the authenticated user: completed, black_border, no_geraldo, current_lcc.

    public function test_map_with_accepted_non_deleted_completion_returns_completed_true(): void
    {
        $this->markTestSkipped('Map with an accepted, non-deleted completion → completed=true');
    }

    public function test_completion_with_black_border_true_returns_black_border_true(): void
    {
        $this->markTestSkipped('Completion with black_border=true → black_border=true');
    }

    public function test_completion_with_no_geraldo_true_returns_no_geraldo_true(): void
    {
        $this->markTestSkipped('Completion with no_geraldo=true → no_geraldo=true');
    }

    public function test_completion_with_current_lcc_returns_current_lcc_true(): void
    {
        $this->markTestSkipped('Completion with current LCC → current_lcc=true');
    }

    public function test_all_four_flags_can_be_true_at_once_for_different_completions_on_same_map(): void
    {
        $this->markTestSkipped('All four flags can be true at once for different completions on the same map');
    }

    public function test_include_medals_without_authentication_returns_422(): void
    {
        $this->markTestSkipped('include=medals without authentication → 422 validation error');
    }

    public function test_map_with_no_completions_all_four_flags_false(): void
    {
        $this->markTestSkipped('Map with no completions at all → all four flags false');
    }

    public function test_map_with_only_deleted_completions_all_flags_false(): void
    {
        $this->markTestSkipped('Map with only deleted completions → all flags false');
    }

    public function test_map_with_only_pending_unaccepted_completions_all_flags_false(): void
    {
        $this->markTestSkipped('Map with only pending (unaccepted) completions → all flags false');
    }

    public function test_completion_belonging_to_different_user_flags_false_for_requesting_user(): void
    {
        $this->markTestSkipped('Completion belonging to a different user → flags false for requesting user');
    }

    public function test_multiple_completions_on_same_map_flags_are_ored(): void
    {
        $this->markTestSkipped('Multiple completions on same map — flags are OR\'d (true if any qualify)');
    }

    public function test_maps_not_in_result_set_do_not_leak_medals_from_other_maps(): void
    {
        $this->markTestSkipped('Maps not in result set do not leak medals from other maps');
    }

    public function test_unauthenticated_get_maps_without_include_medals_returns_200(): void
    {
        $this->markTestSkipped('Unauthenticated GET /maps (no include=medals) still returns 200 — optional auth works');
    }

    public function test_include_medals_for_user_with_completions_on_some_maps_false_only_on_maps_without(): void
    {
        $this->markTestSkipped('include=medals for a user with completions on some maps but not others — false only on maps without');
    }
}
