<?php

namespace Tests\Feature\Formats\Leaderboard;

use App\Constants\FormatConstants;
use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\Map;
use App\Models\User;
use Tests\Abstract\TestsLeaderboardValueBehavior;
use Illuminate\Database\Eloquent\Factories\Sequence;

#[Group('get')]
#[Group('formats')]
#[Group('leaderboard')]
#[Group('nogeraldo')]
class NoGeraldoTest extends TestsLeaderboardValueBehavior
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
        return ['no_geraldo' => true];
    }

    protected function validCompletionMetaFactory(): mixed
    {
        return CompletionMeta::factory()->state(['no_geraldo' => true]);
    }

    protected function invalidCompletionMetaFactory(): mixed
    {
        return CompletionMeta::factory()->state(['no_geraldo' => false]);
    }

    protected function leaderboardValueParam(): string
    {
        return 'no_geraldo';
    }

    // is_no_geraldo_leaderboard_enabled flag

    public function test_no_geraldo_leaderboard_disabled_returns_422(): void
    {
        $this->markTestSkipped('is_no_geraldo_leaderboard_enabled=false on format → GET leaderboard?value=no_geraldo returns 422');
    }

    public function test_no_geraldo_leaderboard_enabled_returns_200(): void
    {
        $this->markTestSkipped('is_no_geraldo_leaderboard_enabled=true on format → GET leaderboard?value=no_geraldo returns 200');
    }

    // Custom test specific to no geraldo (multiple completions on same map)
    public function test_three_ng_completions_same_map_counted_once(): void
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

        $actual = $this->getJson('/api/formats/1/leaderboard?value=no_geraldo')
            ->assertStatus(200)
            ->json();

        $userEntry = collect($actual['data'])->firstWhere('user.discord_id', $user->discord_id);
        $this->assertNotNull($userEntry);
        $this->assertEquals(1, $userEntry['score']);
    }
}
