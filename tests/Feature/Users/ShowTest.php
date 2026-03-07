<?php

namespace Tests\Feature\Users;

use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\LeastCostChimps;
use App\Models\User;
use App\Services\NinjaKiwi\NinjaKiwiApiClient;
use Tests\TestCase;
use Illuminate\Support\Facades\Http;

class ShowTest extends TestCase
{
    #[Group('get')]
    #[Group('users')]
    public function test_user_can_be_retrieved(): void
    {
        $user = User::factory()->create();

        $actual = $this->getJson("/api/users/{$user->discord_id}")
            ->assertStatus(200)
            ->json();

        $expected = User::jsonStructure($user->toArray());

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('users')]
    public function test_returns_404_when_user_not_found(): void
    {
        $actual = $this->getJson('/api/users/999999999')
            ->assertStatus(404)
            ->json();

        $this->assertEquals([
            'message' => 'Not Found',
        ], $actual);
    }

    #[Group('get')]
    #[Group('users')]
    public function test_include_flair_with_no_oak_returns_null_urls(): void
    {
        $user = User::factory()->create(['nk_oak' => null]);

        $actual = $this->getJson("/api/users/{$user->discord_id}?include=flair")
            ->assertStatus(200)
            ->json();

        $expected = User::jsonStructure([
            ...$user->toArray(),
            'avatar_url' => null,
            'banner_url' => null,
        ]);

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('users')]
    public function test_include_flair_with_oak_returns_actual_urls(): void
    {
        $user = User::factory()
            ->withOak('test_oak_123')
            ->cachedFlair()
            ->create();

        NinjaKiwiApiClient::fake([
            'avatar_url' => 'https://example.com/avatar.png',
            'banner_url' => 'https://example.com/banner.png',
        ]);

        $actual = $this->getJson("/api/users/{$user->discord_id}?include=flair")
            ->assertStatus(200)
            ->json();

        $expected = User::jsonStructure([
            ...$user->toArray(),
            'avatar_url' => 'https://example.com/avatar.png',
            'banner_url' => 'https://example.com/banner.png',
        ]);

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('users')]
    public function test_include_random_value_does_not_add_flair_fields(): void
    {
        $user = User::factory()
            ->withOak('test_oak_123')
            ->cachedFlair()
            ->create();

        NinjaKiwiApiClient::fake([
            'avatar_url' => 'https://example.com/avatar.png',
            'banner_url' => 'https://example.com/banner.png',
        ]);

        $actual = $this->getJson("/api/users/{$user->discord_id}?include=random_stuff")
            ->assertStatus(200)
            ->json();

        $expected = User::jsonStructure($user->toArray());

        $this->assertEquals($expected, $actual);
    }

    #[Group("get")]
    #[Group("users")]
    public function test_include_flair_with_oak_when_nk_api_returns_error(): void
    {
        $user = User::factory()->create(["nk_oak" => "test_oak_123"]);

        // Fake NK API to return an error response (404 user not found)
        Http::fake([
            "https://data.ninjakiwi.com/btd6/users/test_oak_123*" => Http::response(null, 400),
        ]);

        $actual = $this->getJson("/api/users/{$user->discord_id}?include=flair")
            ->assertStatus(200)
            ->json();

        // When NK API fails, avatar_url and banner_url should be null
        $this->assertNull($actual["avatar_url"]);
        $this->assertNull($actual["banner_url"]);
    }

    #[Group("get")]
    #[Group("users")]
    public function test_include_medals_for_empty_user_returns_all_zeros(): void
    {
        $user = User::factory()->create();

        $actual = $this->getJson("/api/users/{$user->discord_id}?include=medals")
            ->assertStatus(200)
            ->json();

        $expected = User::jsonStructure([
            ...$user->toArray(),
            'medals' => [
                'wins' => 0,
                'black_border' => 0,
                'no_geraldo' => 0,
                'current_lcc' => 0,
            ],
        ]);

        $this->assertEquals($expected, $actual);
    }

    #[Group("get")]
    #[Group("users")]
    #[Group("medals")]
    public function test_medals_match_seeded_values(): void
    {
        $user = User::factory()->create();

        // Create 4 accepted completions with different flags
        // 1. Black border
        $completion1 = Completion::factory()->create();
        CompletionMeta::factory()
            ->for($completion1)
            ->accepted()
            ->withPlayers([$user])
            ->create([
                'black_border' => true,
                'no_geraldo' => false,
                'lcc_id' => null,
            ]);

        // 2. No geraldo
        $completion2 = Completion::factory()->create();
        CompletionMeta::factory()
            ->for($completion2)
            ->accepted()
            ->withPlayers([$user])
            ->create([
                'black_border' => false,
                'no_geraldo' => true,
                'lcc_id' => null,
            ]);

        // 3. With LCC
        $lcc = LeastCostChimps::factory()->create();
        $completion3 = Completion::factory()->create();
        CompletionMeta::factory()
            ->for($completion3)
            ->accepted()
            ->withPlayers([$user])
            ->create([
                'black_border' => false,
                'no_geraldo' => false,
                'lcc_id' => $lcc->id,
            ]);

        // 4. Normal completion (no flags)
        $completion4 = Completion::factory()->create();
        CompletionMeta::factory()
            ->for($completion4)
            ->accepted()
            ->withPlayers([$user])
            ->create([
                'black_border' => false,
                'no_geraldo' => false,
                'lcc_id' => null,
            ]);

        $actual = $this->getJson("/api/users/{$user->discord_id}?include=medals")
            ->assertStatus(200)
            ->json();

        $expected = User::jsonStructure([
            ...$user->toArray(),
            'medals' => [
                'wins' => 4,
                'black_border' => 1,
                'no_geraldo' => 1,
                'current_lcc' => 1,
            ],
        ]);

        $this->assertEquals($expected, $actual);
    }

    #[Group("get")]
    #[Group("users")]
    #[Group("medals")]
    public function test_medals_aggregate_duplicate_completions_on_same_map(): void
    {
        $user = User::factory()->create();

        // Create ONE map and use its code for all completions
        $map = Completion::factory()->create()->map;

        // Create 4 completions on same map, each with different flags
        // 1. Black border only
        $c1 = Completion::factory()->state(['map_code' => $map->code])->create();
        CompletionMeta::factory()
            ->for($c1)
            ->accepted()
            ->withPlayers([$user])
            ->create([
                'black_border' => true,
                'no_geraldo' => false,
                'lcc_id' => null,
            ]);

        // 2. No geraldo only
        $c2 = Completion::factory()->state(['map_code' => $map->code])->create();
        CompletionMeta::factory()
            ->for($c2)
            ->accepted()
            ->withPlayers([$user])
            ->create([
                'black_border' => false,
                'no_geraldo' => true,
                'lcc_id' => null,
            ]);

        // 3. LCC only
        $lcc = LeastCostChimps::factory()->create();
        $c3 = Completion::factory()->state(['map_code' => $map->code])->create();
        CompletionMeta::factory()
            ->for($c3)
            ->accepted()
            ->withPlayers([$user])
            ->create([
                'black_border' => false,
                'no_geraldo' => false,
                'lcc_id' => $lcc->id,
            ]);

        // 4. Plain completion (no flags)
        $c4 = Completion::factory()->state(['map_code' => $map->code])->create();
        CompletionMeta::factory()
            ->for($c4)
            ->accepted()
            ->withPlayers([$user])
            ->create([
                'black_border' => false,
                'no_geraldo' => false,
                'lcc_id' => null,
            ]);

        $actual = $this->getJson("/api/users/{$user->discord_id}?include=medals")
            ->assertStatus(200)
            ->json();

        $expected = User::jsonStructure([
            ...$user->toArray(),
            'medals' => [
                'wins' => 1,  // Only 1 unique map
                'black_border' => 1,
                'no_geraldo' => 1,
                'current_lcc' => 1,
            ],
        ]);

        $this->assertEquals($expected, $actual);
    }

    #[Group("get")]
    #[Group("users")]
    #[Group("medals")]
    public function test_medals_only_count_active_accepted_completions(): void
    {
        $user = User::factory()->create();

        // 1. Active completion (accepted, not deleted) - should count
        $c1 = Completion::factory()->create();
        CompletionMeta::factory()
            ->for($c1)
            ->accepted()
            ->withPlayers([$user])
            ->create([
                'black_border' => false,
                'no_geraldo' => false,
            ]);

        // 2. Deleted completion (accepted but deleted) - should NOT count
        $c2 = Completion::factory()->create();
        CompletionMeta::factory()
            ->for($c2)
            ->accepted()
            ->deleted()
            ->withPlayers([$user])
            ->create([
                'black_border' => false,
                'no_geraldo' => false,
            ]);

        // 3. Pending completion (not accepted, not deleted) - should NOT count
        $c3 = Completion::factory()->create();
        CompletionMeta::factory()
            ->for($c3)
            ->pending()
            ->withPlayers([$user])
            ->create([
                'black_border' => false,
                'no_geraldo' => false,
            ]);

        $actual = $this->getJson("/api/users/{$user->discord_id}?include=medals")
            ->assertStatus(200)
            ->json();

        $expected = User::jsonStructure([
            ...$user->toArray(),
            'medals' => [
                'wins' => 1,  // Only the active completion counts
                'black_border' => 0,
                'no_geraldo' => 0,
                'current_lcc' => 0,
            ],
        ]);

        $this->assertEquals($expected, $actual);
    }
}
