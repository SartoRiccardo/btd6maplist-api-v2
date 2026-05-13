<?php

namespace Tests\Feature\Formats\Leaderboard;

use App\Constants\FormatConstants;
use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\Format;
use App\Models\Map;
use App\Models\User;
use Tests\Abstract\TestsLeaderboardValueBehavior;
use Illuminate\Database\Eloquent\Factories\Sequence;

#[Group('get')]
#[Group('formats')]
#[Group('leaderboard')]
#[Group('lcc')]
class LCCTest extends TestsLeaderboardValueBehavior
{
    protected function formatId(): int
    {
        return FormatConstants::MAPLIST;
    }

    protected function mapMetaKey(): string
    {
        return 'placement_curver';
    }

    protected function completionMetaFactoryState(): ?string
    {
        return 'lcc';
    }

    protected function completionMetaAttributes(): array
    {
        return [];
    }

    protected function validCompletionMetaFactory(): mixed
    {
        return CompletionMeta::factory()->lcc(5);
    }

    protected function invalidCompletionMetaFactory(): mixed
    {
        return CompletionMeta::factory()->standard();
    }

    protected function leaderboardValueParam(): string
    {
        return 'lccs';
    }

    // Custom test specific to LCC (multiple LCCs on same map, highest leftover counts)
    public function test_multiple_lccs_on_same_map_highest_leftover_counts(): void
    {
        $user = User::factory()->create();
        $map = Map::factory()->withMeta(['placement_curver' => 1])->create();

        $completions = Completion::factory()
            ->count(3)
            ->sequence(fn(Sequence $seq) => [
                'submitted_on' => now()->subSeconds(2 - $seq->index),
            ])
            ->create(['map_code' => $map->code]);

        // Create LCCs with different leftover values: 3, 7, 5
        // The one with leftover=7 should count
        $leftovers = [3, 7, 5];
        foreach ($completions as $index => $completion) {
            CompletionMeta::factory()
                ->lcc($leftovers[$index])
                ->for($completion)
                ->accepted($user->discord_id)
                ->withPlayers([$user])
                ->create(['format_id' => FormatConstants::MAPLIST]);
        }

        $actual = $this->getJson('/api/formats/1/leaderboard?value=lccs')
            ->assertStatus(200)
            ->json();

        $userEntry = collect($actual['data'])->firstWhere('user.discord_id', $user->discord_id);
        $this->assertNotNull($userEntry);
        $this->assertEquals(1, $userEntry['score']);
    }

    // Test that later LCC with higher leftover replaces earlier one
    public function test_later_lcc_with_higher_leftover_replaces_earlier(): void
    {
        $user = User::factory()->create();
        $map = Map::factory()->withMeta(['placement_curver' => 1])->create();

        $completions = Completion::factory()
            ->count(2)
            ->sequence(fn(Sequence $seq) => [
                'submitted_on' => now()->subSeconds(1 - $seq->index),
            ])
            ->create(['map_code' => $map->code]);

        // First LCC with leftover=5, second with leftover=10
        // Second should count (higher leftover)
        $leftovers = [5, 10];
        foreach ($completions as $index => $completion) {
            CompletionMeta::factory()
                ->lcc($leftovers[$index])
                ->for($completion)
                ->accepted($user->discord_id)
                ->withPlayers([$user])
                ->create(['format_id' => FormatConstants::MAPLIST]);
        }

        $actual = $this->getJson('/api/formats/1/leaderboard?value=lccs')
            ->assertStatus(200)
            ->json();

        $userEntry = collect($actual['data'])->firstWhere('user.discord_id', $user->discord_id);
        $this->assertNotNull($userEntry);
        $this->assertEquals(1, $userEntry['score']);
    }

    // is_lcc_leaderboard_enabled flag

    public function test_lcc_leaderboard_disabled_returns_422(): void
    {
        Format::where('id', FormatConstants::MAPLIST)->update(['is_lcc_leaderboard_enabled' => false]);

        $this->getJson('/api/formats/' . FormatConstants::MAPLIST . '/leaderboard?value=lccs')
            ->assertStatus(422);
    }

    public function test_lcc_leaderboard_enabled_returns_200(): void
    {
        Format::where('id', FormatConstants::MAPLIST)->update(['is_lcc_leaderboard_enabled' => true]);

        $this->getJson('/api/formats/' . FormatConstants::MAPLIST . '/leaderboard?value=lccs')
            ->assertStatus(200);
    }

    public function test_disabled_leaderboard_flag_on_nonexistent_format_returns_404(): void
    {
        $this->getJson('/api/formats/99999/leaderboard?value=lccs')
            ->assertStatus(404);
    }

    // Two users, same map, only highest leftover counts per map
    public function test_two_users_same_map_only_highest_leftover_counts(): void
    {
        [$user1, $user2] = User::factory()->count(2)->create();
        $map = Map::factory()->withMeta(['placement_curver' => 1])->create();

        // User1 has LCC with leftover=5, User2 has LCC with leftover=10
        // Only User2 should get credit for this map
        $completions = Completion::factory()
            ->count(2)
            ->create(['map_code' => $map->code]);

        CompletionMeta::factory()
            ->lcc(5)
            ->for($completions[0])
            ->accepted()
            ->withPlayers([$user1])
            ->create(['format_id' => FormatConstants::MAPLIST]);

        CompletionMeta::factory()
            ->lcc(10)
            ->for($completions[1])
            ->accepted()
            ->withPlayers([$user2])
            ->create(['format_id' => FormatConstants::MAPLIST]);

        $actual = $this->getJson('/api/formats/1/leaderboard?value=lccs')
            ->assertStatus(200)
            ->json();

        $user1Entry = collect($actual['data'])->firstWhere('user.discord_id', $user1->discord_id);
        $user2Entry = collect($actual['data'])->firstWhere('user.discord_id', $user2->discord_id);

        $this->assertNull($user1Entry); // User1 has 0 (doesn't appear)
        $this->assertNotNull($user2Entry);
        $this->assertEquals(1, $user2Entry['score']);
    }
}
