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
#[Group('blackborder')]
class BlackBorderTest extends TestsLeaderboardValueBehavior
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
        return null;
    }

    protected function completionMetaAttributes(): array
    {
        return ['black_border' => true];
    }

    protected function validCompletionMetaFactory(): mixed
    {
        return CompletionMeta::factory()->state(['black_border' => true]);
    }

    protected function invalidCompletionMetaFactory(): mixed
    {
        return CompletionMeta::factory()->state(['black_border' => false]);
    }

    protected function leaderboardValueParam(): string
    {
        return 'black_border';
    }

    // is_black_border_leaderboard_enabled flag

    public function test_black_border_leaderboard_disabled_returns_422(): void
    {
        Format::where('id', FormatConstants::MAPLIST)->update(['is_black_border_leaderboard_enabled' => false]);

        $this->getJson('/api/formats/' . FormatConstants::MAPLIST . '/leaderboard?value=black_border')
            ->assertStatus(422);
    }

    public function test_black_border_leaderboard_enabled_returns_200(): void
    {
        Format::where('id', FormatConstants::MAPLIST)->update(['is_black_border_leaderboard_enabled' => true]);

        $this->getJson('/api/formats/' . FormatConstants::MAPLIST . '/leaderboard?value=black_border')
            ->assertStatus(200);
    }

    // Custom test specific to black border (multiple completions on same map)
    public function test_three_bb_completions_same_map_counted_once(): void
    {
        $user = User::factory()->create();
        $map = Map::factory()->withMeta(['placement_curver' => 1])->create();

        $completions = Completion::factory()
            ->count(3)
            ->sequence(fn(Sequence $seq) => [
                'submitted_on' => now()->subSeconds(2 - $seq->index),
            ])
            ->create(['map_code' => $map->code]);

        $this->validCompletionMetaFactory()
            ->count(count($completions))
            ->sequence(fn(Sequence $seq) => [
                'completion_id' => $completions[$seq->index]->id,
                'format_id' => FormatConstants::MAPLIST,
            ])
            ->accepted($user->discord_id)
            ->withPlayers([$user])
            ->create();

        $actual = $this->getJson('/api/formats/1/leaderboard?value=black_border')
            ->assertStatus(200)
            ->json();

        $userEntry = collect($actual['data'])->firstWhere('user.discord_id', $user->discord_id);
        $this->assertNotNull($userEntry);
        $this->assertEquals(1, $userEntry['score']);
    }
}
