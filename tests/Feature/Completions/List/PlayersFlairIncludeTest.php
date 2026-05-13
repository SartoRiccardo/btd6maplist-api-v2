<?php

namespace Tests\Feature\Completions\List;

use App\Constants\FormatConstants;
use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\User;
use App\Services\NinjaKiwi\NinjaKiwiApiClient;
use Tests\TestCase;

class PlayersFlairIncludeTest extends TestCase
{
    // GET /completions?include=players.flair
    // Appends avatar_url and banner_url to each player in completion results.

    private function createCompletionWithPlayer(User $player): CompletionMeta
    {
        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create(['placement_curver' => 1]);
        $completion = Completion::factory()->create(['map_code' => $map->code]);
        $meta = CompletionMeta::factory()->create([
            'completion_id' => $completion->id,
            'format_id' => FormatConstants::MAPLIST,
            'accepted_by_id' => '111111111111111111',
            'deleted_on' => null,
        ]);
        $meta->players()->attach($player->discord_id);
        return $meta;
    }

    public function test_player_with_nk_oak_and_cached_flair_returns_avatar_url_and_banner_url(): void
    {
        $player = User::factory()->create([
            'nk_oak' => 'oak123',
            'cached_avatar_url' => 'https://example.com/avatar.png',
            'cached_banner_url' => 'https://example.com/banner.png',
            'ninjakiwi_cache_expire' => now()->addHour(),
        ]);
        $this->createCompletionWithPlayer($player);

        $actual = $this->getJson('/api/completions?include=players.flair')
            ->assertStatus(200)
            ->json();

        $playerInResponse = $actual['data'][0]['players'][0];
        $this->assertEquals('https://example.com/avatar.png', $playerInResponse['avatar_url']);
        $this->assertEquals('https://example.com/banner.png', $playerInResponse['banner_url']);
    }

    public function test_multiple_players_in_a_completion_all_get_flair_appended(): void
    {
        $player1 = User::factory()->create([
            'cached_avatar_url' => 'https://example.com/av1.png',
            'cached_banner_url' => 'https://example.com/bn1.png',
            'ninjakiwi_cache_expire' => now()->addHour(),
        ]);
        $player2 = User::factory()->create([
            'cached_avatar_url' => 'https://example.com/av2.png',
            'cached_banner_url' => 'https://example.com/bn2.png',
            'ninjakiwi_cache_expire' => now()->addHour(),
        ]);

        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create(['placement_curver' => 1]);
        $completion = Completion::factory()->create(['map_code' => $map->code]);
        $meta = CompletionMeta::factory()->create([
            'completion_id' => $completion->id,
            'format_id' => FormatConstants::MAPLIST,
            'accepted_by_id' => '111111111111111111',
            'deleted_on' => null,
        ]);
        $meta->players()->attach([$player1->discord_id, $player2->discord_id]);

        $actual = $this->getJson('/api/completions?include=players.flair')
            ->assertStatus(200)
            ->json();

        $players = $actual['data'][0]['players'];
        $this->assertCount(2, $players);
        foreach ($players as $p) {
            $this->assertArrayHasKey('avatar_url', $p);
            $this->assertArrayHasKey('banner_url', $p);
        }
    }

    public function test_player_with_no_nk_oak_returns_null_for_both_urls(): void
    {
        $player = User::factory()->create(['nk_oak' => null]);
        $this->createCompletionWithPlayer($player);

        $actual = $this->getJson('/api/completions?include=players.flair')
            ->assertStatus(200)
            ->json();

        $playerInResponse = $actual['data'][0]['players'][0];
        $this->assertArrayHasKey('avatar_url', $playerInResponse);
        $this->assertArrayHasKey('banner_url', $playerInResponse);
        $this->assertNull($playerInResponse['avatar_url']);
        $this->assertNull($playerInResponse['banner_url']);
    }

    public function test_nk_api_error_for_player_returns_null_urls_rest_of_response_still_returned(): void
    {
        $player = User::factory()->create([
            'nk_oak' => 'someoak',
            'cached_avatar_url' => null,
            'cached_banner_url' => null,
            'ninjakiwi_cache_expire' => now()->subMinute(),
        ]);
        $this->createCompletionWithPlayer($player);

        NinjaKiwiApiClient::fake(['avatar_url' => null, 'banner_url' => null]);

        $actual = $this->getJson('/api/completions?include=players.flair')
            ->assertStatus(200)
            ->json();

        $this->assertNotEmpty($actual['data']);
        $playerInResponse = $actual['data'][0]['players'][0];
        $this->assertNull($playerInResponse['avatar_url']);
        $this->assertNull($playerInResponse['banner_url']);
    }

    public function test_include_without_players_flair_does_not_add_flair_fields(): void
    {
        $player = User::factory()->create([
            'cached_avatar_url' => 'https://example.com/avatar.png',
            'ninjakiwi_cache_expire' => now()->addHour(),
        ]);
        $this->createCompletionWithPlayer($player);

        // map.metadata is a valid include that doesn't trigger flair
        $actual = $this->getJson('/api/completions?include=map.metadata')
            ->assertStatus(200)
            ->json();

        $playerInResponse = $actual['data'][0]['players'][0];
        $this->assertArrayNotHasKey('avatar_url', $playerInResponse);
        $this->assertArrayNotHasKey('banner_url', $playerInResponse);
    }

    public function test_include_absent_no_flair_fields_on_players(): void
    {
        $player = User::factory()->create([
            'cached_avatar_url' => 'https://example.com/avatar.png',
            'ninjakiwi_cache_expire' => now()->addHour(),
        ]);
        $this->createCompletionWithPlayer($player);

        $actual = $this->getJson('/api/completions')
            ->assertStatus(200)
            ->json();

        $playerInResponse = $actual['data'][0]['players'][0];
        $this->assertArrayNotHasKey('avatar_url', $playerInResponse);
        $this->assertArrayNotHasKey('banner_url', $playerInResponse);
    }
}
