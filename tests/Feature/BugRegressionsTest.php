<?php

namespace Tests\Feature;

use App\Constants\FormatConstants;
use App\Models\AchievementRole;
use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\Config;
use App\Models\Format;
use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\User;
use App\Models\Verification;
use Tests\TestCase;

class BugRegressionsTest extends TestCase
{
    // Achievement Role: for_first validation only runs when for_first=true

    private function achievementRolePayload(bool $forFirst = false, int $threshold = 5): array
    {
        return [
            'lb_format' => FormatConstants::MAPLIST,
            'lb_type' => 'black_border',
            'threshold' => $threshold,
            'for_first' => $forFirst,
            'name' => 'Test Role',
            'clr_border' => 0,
            'clr_inner' => 0,
        ];
    }

    private function achievementActor(): User
    {
        return $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:achievement_roles']]);
    }

    public function test_creating_achievement_role_with_for_first_false_and_another_non_for_first_role_exists_returns_201(): void
    {
        $actor = $this->achievementActor();
        AchievementRole::factory()->create([
            'lb_format' => FormatConstants::MAPLIST,
            'lb_type' => 'black_border',
            'for_first' => false,
            'threshold' => 1,
        ]);

        $this->actingAs($actor, 'discord')
            ->postJson('/api/roles/achievement', $this->achievementRolePayload(false, 2))
            ->assertStatus(201);
    }

    public function test_creating_achievement_role_with_for_first_true_when_another_for_first_exists_returns_422(): void
    {
        $actor = $this->achievementActor();
        AchievementRole::factory()->firstPlace()->create([
            'lb_format' => FormatConstants::MAPLIST,
            'lb_type' => 'black_border',
        ]);

        $this->actingAs($actor, 'discord')
            ->postJson('/api/roles/achievement', $this->achievementRolePayload(true))
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('for_first');
    }

    public function test_updating_achievement_role_to_for_first_false_when_for_first_role_exists_no_validation_error(): void
    {
        $actor = $this->achievementActor();
        AchievementRole::factory()->firstPlace()->create([
            'lb_format' => FormatConstants::MAPLIST,
            'lb_type' => 'black_border',
        ]);
        $role = AchievementRole::factory()->create([
            'lb_format' => FormatConstants::MAPLIST,
            'lb_type' => 'black_border',
            'for_first' => false,
            'threshold' => 5,
        ]);

        // Update the second role to for_first=false (no change) — should not trigger validation error
        $this->actingAs($actor, 'discord')
            ->putJson("/api/roles/achievement/{$role->id}", array_merge($this->achievementRolePayload(false, 5), [
                'name' => $role->name,
            ]))
            ->assertStatus(200);
    }

    // Map update: deletes all verifications (not just version-null ones)

    private function mapUpdatePayload(Map $map): array
    {
        return [
            'name' => $map->name,
            'placement_curver' => 1,
        ];
    }

    public function test_after_updating_map_version_specific_verifications_are_removed(): void
    {
        $user = $this->createUserWithPermissions([null => ['edit:map']]);
        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create(['placement_curver' => 1]);
        $verifier = User::factory()->create();
        Verification::factory()->create(['map_code' => $map->code, 'user_id' => $verifier->discord_id, 'version' => 441]);

        $this->actingAs($user, 'discord')
            ->putJson("/api/maps/{$map->code}", $this->mapUpdatePayload($map))
            ->assertStatus(204);

        $this->assertDatabaseMissing('verifications', ['map_code' => $map->code]);
    }

    public function test_after_updating_map_with_only_version_null_verifications_those_are_also_removed(): void
    {
        $user = $this->createUserWithPermissions([null => ['edit:map']]);
        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create(['placement_curver' => 1]);
        $verifier = User::factory()->create();
        Verification::factory()->create(['map_code' => $map->code, 'user_id' => $verifier->discord_id, 'version' => null]);

        $this->actingAs($user, 'discord')
            ->putJson("/api/maps/{$map->code}", $this->mapUpdatePayload($map))
            ->assertStatus(204);

        $this->assertDatabaseMissing('verifications', ['map_code' => $map->code]);
    }

    public function test_new_verifications_can_be_added_cleanly_after_update_with_no_leftover_old_ones(): void
    {
        $user = $this->createUserWithPermissions([null => ['edit:map']]);
        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create(['placement_curver' => 1]);
        $verifier1 = User::factory()->create();
        $verifier2 = User::factory()->create();
        Verification::factory()->create(['map_code' => $map->code, 'user_id' => $verifier1->discord_id, 'version' => null]);

        $this->actingAs($user, 'discord')
            ->putJson("/api/maps/{$map->code}", array_merge($this->mapUpdatePayload($map), [
                'verifiers' => [['user_id' => $verifier2->discord_id, 'version' => null]],
            ]))
            ->assertStatus(204);

        // Only the new verifier should exist
        $this->assertEquals(1, Verification::where('map_code', $map->code)->count());
        $this->assertDatabaseHas('verifications', ['map_code' => $map->code, 'user_id' => $verifier2->discord_id]);
        $this->assertDatabaseMissing('verifications', ['map_code' => $map->code, 'user_id' => $verifier1->discord_id]);
    }

    // Verification list re-indexed after filter

    public function test_get_map_with_filtered_verifications_returns_clean_sequential_json_array(): void
    {
        Config::factory()->type('int')->name('current_btd6_ver')->create(['value' => '441']);

        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create(['placement_curver' => 1]);
        $v1 = User::factory()->create();
        $v2 = User::factory()->create();
        $v3 = User::factory()->create();

        // version 441 (current) — kept
        Verification::factory()->create(['map_code' => $map->code, 'user_id' => $v1->discord_id, 'version' => 441]);
        // version 440 (old) — filtered out
        Verification::factory()->create(['map_code' => $map->code, 'user_id' => $v2->discord_id, 'version' => 440]);
        // null — kept
        Verification::factory()->create(['map_code' => $map->code, 'user_id' => $v3->discord_id, 'version' => null]);

        $actual = $this->getJson("/api/maps/{$map->code}")
            ->assertStatus(200)
            ->json();

        $verifications = $actual['verifications'];
        $this->assertCount(2, $verifications);
        // Sequential keys (JSON array, not gapped object)
        $this->assertEquals(range(0, count($verifications) - 1), array_keys($verifications));
    }

    // Platform role list shows ALL roles (not just internal)

    public function test_non_internal_roles_appear_in_platform_roles_response(): void
    {
        $role = \App\Models\Role::factory()->create(['internal' => false]);

        $ids = collect($this->getJson('/api/roles/platform')->assertStatus(200)->json('data'))->pluck('id')->toArray();
        $this->assertContains($role->id, $ids);
    }

    public function test_internal_roles_appear_in_platform_roles_response(): void
    {
        $role = \App\Models\Role::factory()->internal()->create();

        $ids = collect($this->getJson('/api/roles/platform')->assertStatus(200)->json('data'))->pluck('id')->toArray();
        $this->assertContains($role->id, $ids);
    }

    public function test_mix_of_internal_and_non_internal_roles_all_returned(): void
    {
        $internal = \App\Models\Role::factory()->internal()->create();
        $nonInternal = \App\Models\Role::factory()->create(['internal' => false]);

        $ids = collect($this->getJson('/api/roles/platform')->assertStatus(200)->json('data'))->pluck('id')->toArray();
        $this->assertContains($internal->id, $ids);
        $this->assertContains($nonInternal->id, $ids);
    }

    // all_leaderboards view: no_geraldo called with correct format ID

    private function createNgCompletion(User $user, int $formatId): void
    {
        $metaAttrs = match ($formatId) {
            FormatConstants::MAPLIST => ['placement_curver' => 1],
            FormatConstants::EXPERT_LIST => ['difficulty' => 1],
            default => [],
        };
        $map = Map::factory()->withMeta($metaAttrs)->create();
        $completion = Completion::factory()->create(['map_code' => $map->code]);
        CompletionMeta::factory()->create([
            'completion_id' => $completion->id,
            'format_id' => $formatId,
            'accepted_by_id' => '111111111111111111',
            'black_border' => false,
            'no_geraldo' => true,
            'lcc_id' => null,
            'deleted_on' => null,
            'created_on' => now()->subSeconds(2),
        ])->players()->attach($user->discord_id);
    }

    public function test_format_1_no_geraldo_leaderboard_returns_completions_for_format_1_not_format_51(): void
    {
        Format::where('id', FormatConstants::MAPLIST)->update(['is_no_geraldo_enabled' => true, 'is_no_geraldo_leaderboard_enabled' => true]);
        Format::where('id', FormatConstants::EXPERT_LIST)->update(['is_no_geraldo_leaderboard_enabled' => true]);

        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $this->createNgCompletion($userA, FormatConstants::MAPLIST);
        $this->createNgCompletion($userB, FormatConstants::EXPERT_LIST);

        $actual = $this->getJson('/api/formats/' . FormatConstants::MAPLIST . '/leaderboard?value=no_geraldo')
            ->assertStatus(200)
            ->json('data');

        $ids = collect($actual)->pluck('user.discord_id')->toArray();
        $this->assertContains($userA->discord_id, $ids);
        $this->assertNotContains($userB->discord_id, $ids);
    }

    public function test_format_51_no_geraldo_leaderboard_is_unaffected(): void
    {
        Format::where('id', FormatConstants::MAPLIST)->update(['is_no_geraldo_enabled' => true, 'is_no_geraldo_leaderboard_enabled' => true]);
        Format::where('id', FormatConstants::EXPERT_LIST)->update(['is_no_geraldo_leaderboard_enabled' => true]);

        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $this->createNgCompletion($userA, FormatConstants::MAPLIST);
        $this->createNgCompletion($userB, FormatConstants::EXPERT_LIST);

        $actual = $this->getJson('/api/formats/' . FormatConstants::EXPERT_LIST . '/leaderboard?value=no_geraldo')
            ->assertStatus(200)
            ->json('data');

        $ids = collect($actual)->pluck('user.discord_id')->toArray();
        $this->assertContains($userB->discord_id, $ids);
        $this->assertNotContains($userA->discord_id, $ids);
    }

    public function test_user_no_geraldo_score_in_format_1_matches_actual_format_1_completions(): void
    {
        Format::where('id', FormatConstants::MAPLIST)->update(['is_no_geraldo_enabled' => true, 'is_no_geraldo_leaderboard_enabled' => true]);

        $user = User::factory()->create();
        $this->createNgCompletion($user, FormatConstants::MAPLIST);
        $this->createNgCompletion($user, FormatConstants::MAPLIST);
        $this->createNgCompletion($user, FormatConstants::MAPLIST);

        $actual = $this->getJson('/api/formats/' . FormatConstants::MAPLIST . '/leaderboard?value=no_geraldo')
            ->assertStatus(200)
            ->json('data');

        $entry = collect($actual)->firstWhere('user.discord_id', $user->discord_id);
        $this->assertNotNull($entry);
        $this->assertEquals(3, $entry['score']);
    }

    // Completion format filter handles empty array correctly

    public function test_omitting_format_id_returns_completions_across_all_formats(): void
    {
        $player = User::factory()->create();
        $map1 = Map::factory()->withMeta(['placement_curver' => 1])->create();
        $map2 = Map::factory()->withMeta(['difficulty' => 1])->create();

        $this->createAcceptedCompletion($map1->code, FormatConstants::MAPLIST, $player);
        $this->createAcceptedCompletion($map2->code, FormatConstants::EXPERT_LIST, $player);

        $actual = $this->getJson('/api/completions')
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(2, $actual);
    }

    public function test_providing_format_id_filters_correctly(): void
    {
        $player = User::factory()->create();
        $map1 = Map::factory()->withMeta(['placement_curver' => 1])->create();
        $map2 = Map::factory()->withMeta(['difficulty' => 1])->create();

        $this->createAcceptedCompletion($map1->code, FormatConstants::MAPLIST, $player);
        $this->createAcceptedCompletion($map2->code, FormatConstants::EXPERT_LIST, $player);

        $actual = $this->getJson('/api/completions?format_id=' . FormatConstants::MAPLIST)
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(1, $actual);
        $this->assertEquals(FormatConstants::MAPLIST, $actual[0]['format_id']);
    }

    // MapService: per-field validation errors for meta_fields

    public function test_meta_fields_validation_failure_returns_per_field_error_messages(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $response = $this->actingAs($user, 'discord')
            ->postJson('/api/maps', ['code' => 'REGTEST1', 'name' => 'Test Map'])
            ->assertStatus(422)
            ->json();

        foreach (['placement_curver', 'placement_allver', 'difficulty', 'botb_difficulty', 'remake_of'] as $field) {
            $this->assertArrayHasKey($field, $response['errors']);
        }
    }

    public function test_meta_fields_validation_error_message_is_at_least_one_of_these_must_be_set(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $response = $this->actingAs($user, 'discord')
            ->postJson('/api/maps', ['code' => 'REGTEST2', 'name' => 'Test Map'])
            ->assertStatus(422)
            ->json();

        foreach (['placement_curver', 'placement_allver', 'difficulty', 'botb_difficulty', 'remake_of'] as $field) {
            $this->assertEquals('At least one of these must be set', $response['errors'][$field][0]);
        }
    }

    // Helpers

    private function createAcceptedCompletion(string $mapCode, int $formatId, User $player): void
    {
        $completion = Completion::factory()->create(['map_code' => $mapCode]);
        $meta = CompletionMeta::factory()->create([
            'completion_id' => $completion->id,
            'format_id' => $formatId,
            'accepted_by_id' => '111111111111111111',
            'deleted_on' => null,
            'created_on' => now()->subSeconds(2),
        ]);
        $meta->players()->attach($player->discord_id);
    }
}
