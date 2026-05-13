<?php

namespace Tests\Feature\Users;

use App\Constants\FormatConstants;
use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\Role;
use App\Models\RoleFormatPermission;
use App\Models\User;
use Tests\TestCase;

class IncludeRanksAndPermissionsTest extends TestCase
{
    // GET /users/{id}?include=permissions,ranks

    // include=permissions
    // Returns user's permissions grouped by permission name → array of format IDs (null = global).

    public function test_user_with_global_permission_returns_permission_with_null_in_format_array(): void
    {
        $user = $this->createUserWithPermissions([null => ['edit:map']]);

        $actual = $this->getJson("/api/users/{$user->discord_id}?include=permissions")
            ->assertStatus(200)
            ->json('permissions');

        $this->assertArrayHasKey('edit:map', $actual);
        $this->assertContains(null, $actual['edit:map']);
    }

    public function test_user_with_format_scoped_permission_returns_permission_with_format_id_in_array(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $actual = $this->getJson("/api/users/{$user->discord_id}?include=permissions")
            ->assertStatus(200)
            ->json('permissions');

        $this->assertArrayHasKey('edit:map', $actual);
        $this->assertContains(FormatConstants::MAPLIST, $actual['edit:map']);
    }

    public function test_user_with_same_permission_on_multiple_formats_returns_all_format_ids(): void
    {
        $user = $this->createUserWithPermissions([
            FormatConstants::MAPLIST => ['edit:map'],
            FormatConstants::EXPERT_LIST => ['edit:map'],
        ]);

        $actual = $this->getJson("/api/users/{$user->discord_id}?include=permissions")
            ->assertStatus(200)
            ->json('permissions');

        $this->assertArrayHasKey('edit:map', $actual);
        $this->assertContains(FormatConstants::MAPLIST, $actual['edit:map']);
        $this->assertContains(FormatConstants::EXPERT_LIST, $actual['edit:map']);
    }

    public function test_multiple_distinct_permissions_returned_as_separate_keys(): void
    {
        $user = $this->createUserWithPermissions([
            FormatConstants::MAPLIST => ['edit:map'],
            null => ['list:users'],
        ]);

        $actual = $this->getJson("/api/users/{$user->discord_id}?include=permissions")
            ->assertStatus(200)
            ->json('permissions');

        $this->assertArrayHasKey('edit:map', $actual);
        $this->assertArrayHasKey('list:users', $actual);
    }

    public function test_user_with_no_permissions_returns_empty_object(): void
    {
        $user = User::factory()->create();

        $actual = $this->getJson("/api/users/{$user->discord_id}?include=permissions")
            ->assertStatus(200)
            ->json('permissions');

        $this->assertEquals([], $actual);
    }

    public function test_permissions_from_multiple_roles_merged_correctly_no_duplicates(): void
    {
        $user = User::factory()->create();

        // Two roles, each with the same permission on the same format
        $role1 = Role::factory()->create();
        $role2 = Role::factory()->create();
        RoleFormatPermission::factory()->for($role1)->permission('edit:map', FormatConstants::MAPLIST)->create();
        RoleFormatPermission::factory()->for($role2)->permission('edit:map', FormatConstants::MAPLIST)->create();
        $user->roles()->attach([$role1->id, $role2->id]);

        $actual = $this->getJson("/api/users/{$user->discord_id}?include=permissions")
            ->assertStatus(200)
            ->json('permissions');

        $this->assertArrayHasKey('edit:map', $actual);
        $this->assertEquals(1, count($actual['edit:map']));
        $this->assertContains(FormatConstants::MAPLIST, $actual['edit:map']);
    }

    public function test_unknown_include_value_does_not_add_permissions_key(): void
    {
        $user = User::factory()->create();

        $actual = $this->getJson("/api/users/{$user->discord_id}?include=garbage")
            ->assertStatus(200)
            ->json();

        $this->assertArrayNotHasKey('permissions', $actual);
    }

    // include=ranks
    // Returns leaderboard ranks from all_leaderboards view, grouped by format.

    public function test_user_with_completions_has_non_null_placement_for_relevant_lb_type(): void
    {
        $user = User::factory()->create();
        $map = Map::factory()->withMeta(['placement_curver' => 1])->create();
        $completion = Completion::factory()->create(['map_code' => $map->code]);
        CompletionMeta::factory()->create([
            'completion_id' => $completion->id,
            'format_id' => FormatConstants::MAPLIST,
            'accepted_by_id' => '111111111111111111',
            'black_border' => true,
            'deleted_on' => null,
            'created_on' => now()->subSeconds(2),
        ])->players()->attach($user->discord_id);

        $actual = $this->getJson("/api/users/{$user->discord_id}?include=ranks")
            ->assertStatus(200)
            ->json('ranks');

        $formatRanks = collect($actual)->firstWhere('format_id', FormatConstants::MAPLIST);
        $this->assertNotNull($formatRanks);
        $this->assertNotNull($formatRanks['black_border']['placement']);
    }

    public function test_response_grouped_by_format_id_with_points_lccs_no_geraldo_black_border_stats(): void
    {
        $user = User::factory()->create();
        $map = Map::factory()->withMeta(['placement_curver' => 1])->create();
        $completion = Completion::factory()->create(['map_code' => $map->code]);
        CompletionMeta::factory()->create([
            'completion_id' => $completion->id,
            'format_id' => FormatConstants::MAPLIST,
            'accepted_by_id' => '111111111111111111',
            'deleted_on' => null,
            'created_on' => now()->subSeconds(2),
        ])->players()->attach($user->discord_id);

        $actual = $this->getJson("/api/users/{$user->discord_id}?include=ranks")
            ->assertStatus(200)
            ->json('ranks');

        $this->assertIsArray($actual);
        $formatRanks = collect($actual)->firstWhere('format_id', FormatConstants::MAPLIST);
        $this->assertNotNull($formatRanks);
        $this->assertArrayHasKey('format_id', $formatRanks);
        $this->assertArrayHasKey('points', $formatRanks);
        $this->assertArrayHasKey('lccs', $formatRanks);
        $this->assertArrayHasKey('no_geraldo', $formatRanks);
        $this->assertArrayHasKey('black_border', $formatRanks);
    }

    public function test_user_with_no_leaderboard_entries_returns_empty_ranks_array(): void
    {
        $user = User::factory()->create();

        $actual = $this->getJson("/api/users/{$user->discord_id}?include=ranks")
            ->assertStatus(200)
            ->json('ranks');

        $this->assertEquals([], $actual);
    }

    public function test_stats_for_lb_types_user_hasnt_entered_default_to_score_zero_placement_null(): void
    {
        $user = User::factory()->create();
        $map = Map::factory()->withMeta(['placement_curver' => 1])->create();
        $completion = Completion::factory()->create(['map_code' => $map->code]);
        CompletionMeta::factory()->create([
            'completion_id' => $completion->id,
            'format_id' => FormatConstants::MAPLIST,
            'accepted_by_id' => '111111111111111111',
            'black_border' => true,
            'no_geraldo' => false,
            'lcc_id' => null,
            'deleted_on' => null,
            'created_on' => now()->subSeconds(2),
        ])->players()->attach($user->discord_id);

        $actual = $this->getJson("/api/users/{$user->discord_id}?include=ranks")
            ->assertStatus(200)
            ->json('ranks');

        $formatRanks = collect($actual)->firstWhere('format_id', FormatConstants::MAPLIST);
        $this->assertNotNull($formatRanks);

        // black_border has a completion, so score and placement are non-null
        $this->assertNotNull($formatRanks['black_border']['placement']);

        // no_geraldo has no completion → defaults
        $this->assertEquals(0, $formatRanks['no_geraldo']['score']);
        $this->assertNull($formatRanks['no_geraldo']['placement']);

        // lccs has no completion → defaults
        $this->assertEquals(0, $formatRanks['lccs']['score']);
        $this->assertNull($formatRanks['lccs']['placement']);
    }
}
