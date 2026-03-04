<?php

namespace Tests\Feature\Users;

use App\Constants\FormatConstants;
use App\Models\AchievementRole;
use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\Map;
use App\Models\User;
use Tests\TestCase;

class AchievementRolesTest extends TestCase
{
    #[Group('get')]
    #[Group('users')]
    #[Group('achievement_roles')]
    public function test_user_with_no_completions_has_no_achievement_roles(): void
    {
        $user = User::factory()->create();

        $actual = $this->getJson("/api/users/{$user->discord_id}?include=achievement_roles")
            ->assertStatus(200)
            ->json();

        $this->assertEquals([], $actual['achievement_roles']);
    }

    #[Group('get')]
    #[Group('users')]
    #[Group('achievement_roles')]
    public function test_user_below_threshold_gets_no_roles(): void
    {
        AchievementRole::factory()->create([
            'lb_format' => FormatConstants::MAPLIST,
            'lb_type' => 'black_border',
            'threshold' => 5,
            'for_first' => false,
        ]);

        $user = $this->createUserWithBlackBorderCount(2);

        $actual = $this->getJson("/api/users/{$user->discord_id}?include=achievement_roles")
            ->assertStatus(200)
            ->json();

        $this->assertEquals([], $actual['achievement_roles']);
    }

    #[Group('get')]
    #[Group('users')]
    #[Group('achievement_roles')]
    public function test_user_with_exact_threshold_gets_role(): void
    {
        $role = AchievementRole::factory()->create([
            'lb_format' => FormatConstants::MAPLIST,
            'lb_type' => 'black_border',
            'threshold' => 10,
            'for_first' => false,
        ]);

        $user = $this->createUserWithBlackBorderCount(10);

        $actual = $this->getJson("/api/users/{$user->discord_id}?include=achievement_roles")
            ->assertStatus(200)
            ->json();

        $expected = [
            AchievementRole::jsonStructure($role->toArray()),
        ];
        $this->assertEquals($expected, $this->except($actual['achievement_roles'], ['*.id']));
    }

    #[Group('get')]
    #[Group('users')]
    #[Group('achievement_roles')]
    public function test_user_with_multiple_thresholds_gets_highest_role(): void
    {
        $roles = AchievementRole::factory()
            ->count(4)
            ->sequence(fn($seq) => [
                'lb_format' => FormatConstants::MAPLIST,
                'lb_type' => 'black_border',
                'threshold' => [1, 5, 10, 25][$seq->index],
            ])
            ->create();

        $user = $this->createUserWithBlackBorderCount(12);

        $actual = $this->getJson("/api/users/{$user->discord_id}?include=achievement_roles")
            ->assertStatus(200)
            ->json();

        $expected = [
            AchievementRole::jsonStructure($roles[2]->toArray()),
        ];
        $this->assertEquals($expected, $this->except($actual['achievement_roles'], ['*.id']));
    }

    #[Group('get')]
    #[Group('users')]
    #[Group('achievement_roles')]
    public function test_user_with_high_points_gets_highest_threshold_role(): void
    {
        // Create first place role
        AchievementRole::factory()
            ->firstPlace()
            ->create([
                'lb_format' => FormatConstants::MAPLIST,
                'lb_type' => 'black_border',
            ]);

        // Create threshold roles
        $thresholdRoles = AchievementRole::factory()
            ->count(3)
            ->sequence(fn($seq) => [
                'lb_format' => FormatConstants::MAPLIST,
                'lb_type' => 'black_border',
                'threshold' => [10, 25, 50][$seq->index],
            ])
            ->create();

        // User with 50 BB, but NOT first place (other users have more)
        $user = $this->createUserWithBlackBorderCount(50);

        // Create other users with higher scores to ensure user is not #1
        $this->createUserWithBlackBorderCount(75);
        $this->createUserWithBlackBorderCount(100);

        $actual = $this->getJson("/api/users/{$user->discord_id}?include=achievement_roles")
            ->assertStatus(200)
            ->json();

        $expected = [
            AchievementRole::jsonStructure($thresholdRoles[2]->toArray()),
        ];
        $this->assertEquals($expected, $this->except($actual['achievement_roles'], ['*.id']));
    }

    #[Group('get')]
    #[Group('users')]
    #[Group('achievement_roles')]
    public function test_first_place_gets_first_place_role_only(): void
    {
        // Create first place role
        $firstPlaceRole = AchievementRole::factory()
            ->firstPlace()
            ->create([
                'lb_format' => FormatConstants::MAPLIST,
                'lb_type' => 'black_border',
            ]);

        // Create threshold role
        AchievementRole::factory()->create([
            'lb_format' => FormatConstants::MAPLIST,
            'lb_type' => 'black_border',
            'threshold' => 1,
        ]);

        // User with 1 completion (placement=1, no other users)
        $user = $this->createUserWithBlackBorderCount(1);

        $actual = $this->getJson("/api/users/{$user->discord_id}?include=achievement_roles")
            ->assertStatus(200)
            ->json();

        $expected = [
            AchievementRole::jsonStructure($firstPlaceRole->toArray()),
        ];
        $this->assertEquals($expected, $this->except($actual['achievement_roles'], ['*.id']));
    }

    #[Group('get')]
    #[Group('users')]
    #[Group('achievement_roles')]
    public function test_first_place_in_multiple_leaderboards_gets_role_for_each(): void
    {
        // MAPLIST black_border first place role
        $maplistRole = AchievementRole::factory()
            ->firstPlace()
            ->create([
                'lb_format' => FormatConstants::MAPLIST,
                'lb_type' => 'black_border',
            ]);

        // EXPERT_LIST black_border first place role
        $expertRole = AchievementRole::factory()
            ->firstPlace()
            ->create([
                'lb_format' => FormatConstants::EXPERT_LIST,
                'lb_type' => 'black_border',
            ]);

        $user = User::factory()->create();

        // Create 1 BB completion for MAPLIST
        $this->createCompletionMetaForLb($user, FormatConstants::MAPLIST, 'black_border', true);

        // Create 1 BB completion for EXPERT_LIST
        $this->createCompletionMetaForLb($user, FormatConstants::EXPERT_LIST, 'black_border', true);

        $actual = $this->getJson("/api/users/{$user->discord_id}?include=achievement_roles")
            ->assertStatus(200)
            ->json();

        $expected = [
            AchievementRole::jsonStructure($maplistRole->toArray()),
            AchievementRole::jsonStructure($expertRole->toArray()),
        ];
        $this->assertEqualsCanonicalizing($expected, $this->except($actual['achievement_roles'], ['*.id']));
    }

    #[Group('get')]
    #[Group('users')]
    #[Group('achievement_roles')]
    public function test_user_with_multiple_lb_types_gets_one_role_per_type(): void
    {
        // MAPLIST black_border roles
        $bbRole = AchievementRole::factory()->create([
            'lb_format' => FormatConstants::MAPLIST,
            'lb_type' => 'black_border',
            'threshold' => 10,
            'for_first' => false,
        ]);

        // MAPLIST no_geraldo roles
        $ngRole = AchievementRole::factory()->create([
            'lb_format' => FormatConstants::MAPLIST,
            'lb_type' => 'no_geraldo',
            'threshold' => 5,
            'for_first' => false,
        ]);

        $user = User::factory()->create();

        // Create 10 BB completions on different maps
        $this->createUserWithBlackBorderCount(10, $user);

        // Create 5 NG completions on DIFFERENT maps (not same as BB)
        $this->createUserWithNoGeraldoCount(5, $user);

        $actual = $this->getJson("/api/users/{$user->discord_id}?include=achievement_roles")
            ->assertStatus(200)
            ->json();

        $expected = [
            AchievementRole::jsonStructure($bbRole->toArray()),
            AchievementRole::jsonStructure($ngRole->toArray()),
        ];
        $this->assertEqualsCanonicalizing($expected, $this->except($actual['achievement_roles'], ['*.id']));
    }

    protected function createUserWithBlackBorderCount(int $count, ?User $user = null): User
    {
        $user ??= User::factory()->create();

        $maps = Map::factory()
            ->withMeta(['placement_curver' => 1])
            ->count($count)
            ->create();

        $completions = Completion::factory()
            ->count($count)
            ->sequence(fn($seq) => [
                'map_code' => $maps[$seq->index]->code,
            ])
            ->create();

        CompletionMeta::factory()
            ->count($count)
            ->sequence(fn($seq) => [
                'completion_id' => $completions[$seq->index]->id,
                'format_id' => FormatConstants::MAPLIST,
                'black_border' => true,
                'no_geraldo' => false,
                'lcc_id' => null,
            ])
            ->accepted()
            ->withPlayers([$user])
            ->create();

        return $user;
    }

    protected function createUserWithNoGeraldoCount(int $count, ?User $user = null): User
    {
        $user ??= User::factory()->create();

        $maps = Map::factory()
            ->withMeta(['difficulty' => 1])
            ->count($count)
            ->create();

        $completions = Completion::factory()
            ->count($count)
            ->sequence(fn($seq) => [
                'map_code' => $maps[$seq->index]->code,
            ])
            ->create();

        CompletionMeta::factory()
            ->count($count)
            ->sequence(fn($seq) => [
                'completion_id' => $completions[$seq->index]->id,
                'format_id' => FormatConstants::EXPERT_LIST,
                'black_border' => false,
                'no_geraldo' => true,
                'lcc_id' => null,
            ])
            ->accepted()
            ->withPlayers([$user])
            ->create();

        return $user;
    }

    protected function createCompletionMetaForLb(User $user, int $formatId, string $lbType, bool $value): void
    {
        // Maps need proper metadata for leaderboard functions to count them
        $metaAttrs = match ($formatId) {
            FormatConstants::MAPLIST => ['placement_curver' => 1],
            FormatConstants::EXPERT_LIST => ['difficulty' => 1],
            default => [],
        };

        $map = Map::factory()->withMeta($metaAttrs)->create();
        $completion = Completion::factory()->create(['map_code' => $map->code]);

        $meta = [
            'format_id' => $formatId,
            'black_border' => false,
            'no_geraldo' => false,
            'lcc_id' => null,
        ];

        match ($lbType) {
            'black_border' => $meta['black_border'] = $value,
            'no_geraldo' => $meta['no_geraldo'] = $value,
        };

        CompletionMeta::factory()
            ->for($completion)
            ->accepted()
            ->withPlayers([$user])
            ->create($meta);
    }
}
