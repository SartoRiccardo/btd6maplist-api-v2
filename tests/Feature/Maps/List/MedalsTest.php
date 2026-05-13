<?php

namespace Tests\Feature\Maps\List;

use App\Constants\FormatConstants;
use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\User;
use Tests\TestCase;

class MedalsTest extends TestCase
{
    // GET /maps?include=medals
    // Adds per-map medal flags for the authenticated user: completed, black_border, no_geraldo, current_lcc.

    private function createMap(): Map
    {
        return Map::factory()->withMeta(['placement_curver' => 1])->create();
    }

    private function createAcceptedMeta(Map $map, User $user, array $state = []): CompletionMeta
    {
        $completion = Completion::factory()->create(['map_code' => $map->code]);
        $meta = CompletionMeta::factory()->create(array_merge([
            'completion_id' => $completion->id,
            'format_id' => FormatConstants::MAPLIST,
            'accepted_by_id' => '111111111111111111',
            'deleted_on' => null,
            'black_border' => false,
            'no_geraldo' => false,
            'lcc_id' => null,
            'created_on' => now()->subSeconds(2),
        ], $state));
        $meta->players()->attach($user->discord_id);
        return $meta;
    }

    private function getMedals(User $user, Map $map): array
    {
        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps?include=medals&format_id=' . FormatConstants::MAPLIST)
            ->assertStatus(200)
            ->json('data');

        return collect($actual)->firstWhere('code', $map->code)['medals'] ?? [];
    }

    public function test_map_with_accepted_non_deleted_completion_returns_completed_true(): void
    {
        $user = User::factory()->create();
        $map = $this->createMap();
        $this->createAcceptedMeta($map, $user);

        $medals = $this->getMedals($user, $map);
        $this->assertTrue($medals['completed']);
    }

    public function test_completion_with_black_border_true_returns_black_border_true(): void
    {
        $user = User::factory()->create();
        $map = $this->createMap();
        $this->createAcceptedMeta($map, $user, ['black_border' => true]);

        $medals = $this->getMedals($user, $map);
        $this->assertTrue($medals['black_border']);
    }

    public function test_completion_with_no_geraldo_true_returns_no_geraldo_true(): void
    {
        $user = User::factory()->create();
        $map = $this->createMap();
        $this->createAcceptedMeta($map, $user, ['no_geraldo' => true]);

        $medals = $this->getMedals($user, $map);
        $this->assertTrue($medals['no_geraldo']);
    }

    public function test_completion_with_current_lcc_returns_current_lcc_true(): void
    {
        $user = User::factory()->create();
        $map = $this->createMap();
        $completion = Completion::factory()->create(['map_code' => $map->code]);
        $meta = CompletionMeta::factory()->lcc(100)->create([
            'completion_id' => $completion->id,
            'format_id' => FormatConstants::MAPLIST,
            'accepted_by_id' => '111111111111111111',
            'deleted_on' => null,
            'created_on' => now()->subSeconds(2),
        ]);
        $meta->players()->attach($user->discord_id);

        $medals = $this->getMedals($user, $map);
        $this->assertTrue($medals['current_lcc']);
    }

    public function test_all_four_flags_can_be_true_at_once_for_different_completions_on_same_map(): void
    {
        $user = User::factory()->create();
        $map = $this->createMap();

        // Standard completion for `completed`
        $this->createAcceptedMeta($map, $user);

        // BB completion
        $this->createAcceptedMeta($map, $user, ['black_border' => true]);

        // NG completion
        $this->createAcceptedMeta($map, $user, ['no_geraldo' => true]);

        // LCC completion
        $completion = Completion::factory()->create(['map_code' => $map->code]);
        $meta = CompletionMeta::factory()->lcc(100)->create([
            'completion_id' => $completion->id,
            'format_id' => FormatConstants::MAPLIST,
            'accepted_by_id' => '111111111111111111',
            'deleted_on' => null,
            'created_on' => now()->subSeconds(2),
        ]);
        $meta->players()->attach($user->discord_id);

        $medals = $this->getMedals($user, $map);
        $this->assertTrue($medals['completed']);
        $this->assertTrue($medals['black_border']);
        $this->assertTrue($medals['no_geraldo']);
        $this->assertTrue($medals['current_lcc']);
    }

    public function test_include_medals_without_authentication_returns_422(): void
    {
        $this->getJson('/api/maps?include=medals')
            ->assertStatus(422)
            ->assertJsonPath('errors.include.0', 'medals requires authentication');
    }

    public function test_map_with_no_completions_all_four_flags_false(): void
    {
        $user = User::factory()->create();
        $map = $this->createMap();

        $medals = $this->getMedals($user, $map);
        $this->assertFalse($medals['completed']);
        $this->assertFalse($medals['black_border']);
        $this->assertFalse($medals['no_geraldo']);
        $this->assertFalse($medals['current_lcc']);
    }

    public function test_map_with_only_deleted_completions_all_flags_false(): void
    {
        $user = User::factory()->create();
        $map = $this->createMap();
        $this->createAcceptedMeta($map, $user, [
            'deleted_on' => now()->subMinute(),
            'black_border' => true,
            'no_geraldo' => true,
        ]);

        $medals = $this->getMedals($user, $map);
        $this->assertFalse($medals['completed']);
        $this->assertFalse($medals['black_border']);
        $this->assertFalse($medals['no_geraldo']);
        $this->assertFalse($medals['current_lcc']);
    }

    public function test_map_with_only_pending_unaccepted_completions_all_flags_false(): void
    {
        $user = User::factory()->create();
        $map = $this->createMap();
        $this->createAcceptedMeta($map, $user, [
            'accepted_by_id' => null,
            'black_border' => true,
            'no_geraldo' => true,
        ]);

        $medals = $this->getMedals($user, $map);
        $this->assertFalse($medals['completed']);
        $this->assertFalse($medals['black_border']);
        $this->assertFalse($medals['no_geraldo']);
        $this->assertFalse($medals['current_lcc']);
    }

    public function test_completion_belonging_to_different_user_flags_false_for_requesting_user(): void
    {
        $requestingUser = User::factory()->create();
        $otherUser = User::factory()->create();
        $map = $this->createMap();
        $this->createAcceptedMeta($map, $otherUser, [
            'black_border' => true,
            'no_geraldo' => true,
        ]);

        $medals = $this->getMedals($requestingUser, $map);
        $this->assertFalse($medals['completed']);
        $this->assertFalse($medals['black_border']);
        $this->assertFalse($medals['no_geraldo']);
        $this->assertFalse($medals['current_lcc']);
    }

    public function test_multiple_completions_on_same_map_flags_are_ored(): void
    {
        $user = User::factory()->create();
        $map = $this->createMap();
        $this->createAcceptedMeta($map, $user, ['black_border' => true]);
        $this->createAcceptedMeta($map, $user, ['no_geraldo' => true]);

        $medals = $this->getMedals($user, $map);
        $this->assertTrue($medals['black_border']);
        $this->assertTrue($medals['no_geraldo']);
    }

    public function test_maps_not_in_result_set_do_not_leak_medals_from_other_maps(): void
    {
        $user = User::factory()->create();
        $mapWithMedal = $this->createMap();
        $mapWithoutMedal = $this->createMap();
        $this->createAcceptedMeta($mapWithMedal, $user, ['black_border' => true]);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps?include=medals&format_id=' . FormatConstants::MAPLIST)
            ->assertStatus(200)
            ->json('data');

        $noMedalEntry = collect($actual)->firstWhere('code', $mapWithoutMedal->code);
        $this->assertFalse($noMedalEntry['medals']['black_border']);
    }

    public function test_current_lcc_is_false_when_user_lcc_is_beaten_by_higher_leftover(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $map = $this->createMap();

        CompletionMeta::factory()->lcc(25000)->withPlayers([$userA])->create([
            'completion_id' => Completion::factory()->create(['map_code' => $map->code])->id,
            'format_id' => FormatConstants::MAPLIST,
            'created_on' => now()->subSeconds(4),
        ]);
        CompletionMeta::factory()->lcc(29000)->withPlayers([$userB])->create([
            'completion_id' => Completion::factory()->create(['map_code' => $map->code])->id,
            'format_id' => FormatConstants::MAPLIST,
            'created_on' => now()->subSeconds(2),
        ]);

        $medalsA = $this->getMedals($userA, $map);
        $this->assertFalse($medalsA['current_lcc']);

        $medalsB = $this->getMedals($userB, $map);
        $this->assertTrue($medalsB['current_lcc']);
    }

    public function test_current_lcc_is_false_when_user_holds_current_lcc_only_in_another_format(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $map = Map::factory()->withMeta(['placement_curver' => 1, 'placement_allver' => 1])->create();

        // User A's format 1 LCC (25000) is beaten by user B (29000)
        CompletionMeta::factory()->lcc(25000)->withPlayers([$userA])->create([
            'completion_id' => Completion::factory()->create(['map_code' => $map->code])->id,
            'format_id' => FormatConstants::MAPLIST,
            'created_on' => now()->subSeconds(6),
        ]);
        CompletionMeta::factory()->lcc(29000)->withPlayers([$userB])->create([
            'completion_id' => Completion::factory()->create(['map_code' => $map->code])->id,
            'format_id' => FormatConstants::MAPLIST,
            'created_on' => now()->subSeconds(4),
        ]);

        // User A is the sole (current) LCC holder for format 2 on the same map
        CompletionMeta::factory()->lcc(25000)->withPlayers([$userA])->create([
            'completion_id' => Completion::factory()->create(['map_code' => $map->code])->id,
            'format_id' => FormatConstants::MAPLIST_ALL_VERSIONS,
            'created_on' => now()->subSeconds(2),
        ]);

        // Viewing format 1 map list: user A should NOT have current_lcc = true
        $medalsF1 = $this->getMedals($userA, $map);
        $this->assertFalse($medalsF1['current_lcc']);

        // Viewing format 2 map list: user A still holds the current LCC there
        $f2Data = $this->actingAs($userA, 'discord')
            ->getJson('/api/maps?include=medals&format_id=' . FormatConstants::MAPLIST_ALL_VERSIONS)
            ->assertStatus(200)
            ->json('data');
        $medalsF2 = collect($f2Data)->firstWhere('code', $map->code)['medals'] ?? [];
        $this->assertTrue($medalsF2['current_lcc']);
    }

    public function test_unauthenticated_get_maps_without_include_medals_returns_200(): void
    {
        $this->getJson('/api/maps')->assertStatus(200);
    }

    public function test_include_medals_for_user_with_completions_on_some_maps_false_only_on_maps_without(): void
    {
        $user = User::factory()->create();
        $mapWith = $this->createMap();
        $mapWithout = $this->createMap();
        $this->createAcceptedMeta($mapWith, $user);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps?include=medals&format_id=' . FormatConstants::MAPLIST)
            ->assertStatus(200)
            ->json('data');

        $withEntry = collect($actual)->firstWhere('code', $mapWith->code);
        $withoutEntry = collect($actual)->firstWhere('code', $mapWithout->code);
        $this->assertTrue($withEntry['medals']['completed']);
        $this->assertFalse($withoutEntry['medals']['completed']);
    }
}
