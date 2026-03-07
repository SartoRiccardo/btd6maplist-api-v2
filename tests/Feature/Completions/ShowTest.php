<?php

namespace Tests\Feature\Completions;

use App\Constants\FormatConstants;
use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\LeastCostChimps;
use App\Models\MapListMeta;
use App\Models\User;
use App\Services\NinjaKiwi\NinjaKiwiApiClient;
use Tests\Helpers\CompletionTestHelper;
use Tests\TestCase;
use PHPUnit\Metadata\Group;

class ShowTest extends TestCase
{
    // ============================================
    // Basic Response Tests
    // ============================================

    #[Group('get')]
    #[Group('completions')]
    public function test_returns_completion_with_default_parameters(): void
    {
        $completion = Completion::factory()->create();
        $players = User::factory()->count(2)->create()->all();
        $meta = CompletionMeta::factory()
            ->for($completion)
            ->accepted()
            ->withPlayers($players)
            ->create();

        $meta->load(['completion.map', 'players', 'lcc', 'acceptedBy']);

        $actual = $this->getJson("/api/completions/{$completion->id}")
            ->assertStatus(200)
            ->json();

        $expected = CompletionTestHelper::mergeCompletionMeta($completion, $meta, [
            'is_current_lcc' => false,
        ]);

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('completions')]
    public function test_returns_404_when_completion_not_found(): void
    {
        $this->getJson('/api/completions/999999')
            ->assertStatus(404)
            ->assertJson(['message' => 'Not Found']);
    }

    #[Group('get')]
    #[Group('completions')]
    public function test_returns_deleted_completion_with_deleted_on_field(): void
    {
        $completion = Completion::factory()->create();
        CompletionMeta::factory()
            ->for($completion)
            ->accepted()
            ->create([
                'created_on' => now()->subHour(),
                'deleted_on' => now()->subMinutes(30),
            ]);

        // Query at a time after the meta was deleted
        $timestamp = now()->subMinutes(15)->timestamp;

        $actual = $this->getJson("/api/completions/{$completion->id}?timestamp={$timestamp}")
            ->assertStatus(200)
            ->json();

        // Should return the completion with deleted_on field set
        $this->assertNotNull($actual['deleted_on']);
    }

    // ============================================
    // is_current_lcc Tests
    // ============================================

    #[Group('get')]
    #[Group('completions')]
    #[Group('lcc')]
    public function test_is_current_lcc_is_true_when_lcc_is_current(): void
    {
        $player = User::factory()->create();
        $map = Completion::factory()->create()->map;

        // Create two completions with different LCCs on the same map
        // Small LCC (should NOT be current)
        $completionSmall = Completion::factory()->create(['map_code' => $map->code]);
        CompletionMeta::factory()
            ->for($completionSmall)
            ->accepted()
            ->withPlayers([$player])
            ->lcc(1000)
            ->create(['format_id' => FormatConstants::MAPLIST]);

        // Big LCC (should be current)
        $completion = Completion::factory()->create(['map_code' => $map->code]);
        $meta = CompletionMeta::factory()
            ->for($completion)
            ->accepted()
            ->withPlayers([$player])
            ->lcc(5000)
            ->create(['format_id' => FormatConstants::MAPLIST]);

        $meta->load(['completion.map', 'players', 'lcc', 'acceptedBy']);

        $actual = $this->getJson("/api/completions/{$completion->id}")
            ->assertStatus(200)
            ->json();

        $expected = CompletionTestHelper::mergeCompletionMeta($completion, $meta, [
            'is_current_lcc' => true,
        ]);

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('lcc')]
    public function test_is_current_lcc_is_false_when_lcc_is_not_current(): void
    {
        $player = User::factory()->create();
        $map = Completion::factory()->create()->map;

        // Create two completions with different LCCs on the same map
        // Big LCC (becomes current)
        $completionBig = Completion::factory()->create(['map_code' => $map->code]);
        CompletionMeta::factory()
            ->for($completionBig)
            ->accepted()
            ->withPlayers([$player])
            ->lcc(5000)
            ->create(['format_id' => FormatConstants::MAPLIST]);

        // Small LCC (should NOT be current)
        $completion = Completion::factory()->create(['map_code' => $map->code]);
        $meta = CompletionMeta::factory()
            ->for($completion)
            ->accepted()
            ->withPlayers([$player])
            ->lcc(1000)
            ->create(['format_id' => FormatConstants::MAPLIST]);

        $meta->load(['completion.map', 'players', 'lcc', 'acceptedBy']);

        $actual = $this->getJson("/api/completions/{$completion->id}")
            ->assertStatus(200)
            ->json();

        $expected = CompletionTestHelper::mergeCompletionMeta($completion, $meta, [
            'is_current_lcc' => false,
        ]);

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('lcc')]
    public function test_is_current_lcc_is_false_when_no_lcc(): void
    {
        $completion = Completion::factory()->create();
        $players = User::factory()->count(2)->create()->all();
        $meta = CompletionMeta::factory()
            ->for($completion)
            ->accepted()
            ->withPlayers($players)
            ->create(['lcc_id' => null]);

        $meta->load(['completion.map', 'players', 'lcc', 'acceptedBy']);

        $actual = $this->getJson("/api/completions/{$completion->id}")
            ->assertStatus(200)
            ->json();

        $expected = CompletionTestHelper::mergeCompletionMeta($completion, $meta, [
            'is_current_lcc' => false,
        ]);

        $this->assertEquals($expected, $actual);
    }

    // ============================================
    // Validation Tests
    // ============================================

    #[Group('get')]
    #[Group('completions')]
    #[Group('validation')]
    public function test_timestamp_must_be_integer(): void
    {
        $completion = Completion::factory()->create();
        CompletionMeta::factory()
            ->for($completion)
            ->accepted()
            ->create();

        $this->getJson("/api/completions/{$completion->id}?timestamp=invalid")
            ->assertStatus(422)
            ->assertJsonValidationErrors('timestamp');
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('validation')]
    public function test_timestamp_must_be_positive_or_zero(): void
    {
        $completion = Completion::factory()->create();
        CompletionMeta::factory()
            ->for($completion)
            ->accepted()
            ->create();

        $this->getJson("/api/completions/{$completion->id}?timestamp=-1")
            ->assertStatus(422)
            ->assertJsonValidationErrors('timestamp');
    }

    // ============================================
    // Timestamp Tests
    // ============================================

    #[Group('get')]
    #[Group('completions')]
    #[Group('timestamp')]
    public function test_returns_meta_state_at_past_timestamp(): void
    {
        $completion = Completion::factory()->create();
        $players = User::factory()->count(2)->create()->all();

        // Create old meta (different black_border value)
        $oldMeta = CompletionMeta::factory()
            ->for($completion)
            ->accepted()
            ->withPlayers($players)
            ->create([
                'black_border' => true,
                'created_on' => now()->subHours(3),
                'deleted_on' => now()->subHours(2),
            ]);

        // Create current meta (different black_border value)
        CompletionMeta::factory()
            ->for($completion)
            ->accepted()
            ->withPlayers($players)
            ->create([
                'black_border' => false,
                'created_on' => now()->subHour(),
            ]);

        // Query at a time when the old meta was active
        $timestamp = now()->subHours(2)->subMinutes(30)->timestamp;

        $oldMeta->load(['completion.map', 'players', 'lcc', 'acceptedBy']);

        $actual = $this->getJson("/api/completions/{$completion->id}?timestamp={$timestamp}")
            ->assertStatus(200)
            ->json();

        $expected = CompletionTestHelper::mergeCompletionMeta($completion, $oldMeta, [
            'is_current_lcc' => false,
        ]);

        $this->assertEquals($expected, $actual);
        $this->assertTrue($actual['black_border']);
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('timestamp')]
    public function test_returns_404_when_querying_before_creation(): void
    {
        $completion = Completion::factory()->create();
        CompletionMeta::factory()
            ->for($completion)
            ->accepted()
            ->create([
                'created_on' => now()->subHour(),
            ]);

        // Query before the meta was created
        $timestamp = now()->subHours(2)->timestamp;

        $this->getJson("/api/completions/{$completion->id}?timestamp={$timestamp}")
            ->assertStatus(404)
            ->assertJson(['message' => 'Not Found']);
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('timestamp')]
    public function test_returns_deleted_completion_when_queried_after_deletion(): void
    {
        $completion = Completion::factory()->create();
        $players = User::factory()->count(2)->create()->all();
        $deletedMeta = CompletionMeta::factory()
            ->for($completion)
            ->accepted()
            ->withPlayers($players)
            ->create([
                'created_on' => now()->subHours(2),
                'deleted_on' => now()->subHour(),
            ]);

        // Query after the meta was deleted
        $timestamp = now()->subMinutes(30)->timestamp;

        $deletedMeta->load(['completion.map', 'players', 'lcc', 'acceptedBy']);

        $actual = $this->getJson("/api/completions/{$completion->id}?timestamp={$timestamp}")
            ->assertStatus(200)
            ->json();

        $expected = CompletionTestHelper::mergeCompletionMeta($completion, $deletedMeta, [
            'is_current_lcc' => false,
        ]);

        $this->assertEquals($expected, $actual);
        $this->assertNotNull($actual['deleted_on']);
    }

    // ============================================
    // Include Parameter Tests
    // ============================================

    #[Group('get')]
    #[Group('completions')]
    #[Group('include')]
    public function test_include_map_metadata_adds_map_metadata(): void
    {
        $completion = Completion::factory()->create();
        $players = User::factory()->count(2)->create()->all();
        $meta = CompletionMeta::factory()
            ->for($completion)
            ->accepted()
            ->withPlayers($players)
            ->create();

        $map = $completion->map;
        $mapMeta = MapListMeta::with('retroMap.game')
            ->where('code', $map->code)
            ->first();

        $meta->load(['completion.map', 'players', 'lcc', 'acceptedBy']);

        $actual = $this->getJson("/api/completions/{$completion->id}?include=map.metadata")
            ->assertStatus(200)
            ->json();

        // Build expected with map metadata merged in
        $expectedBase = CompletionTestHelper::mergeCompletionMeta($completion, $meta, [
            'is_current_lcc' => false,
        ]);

        // Manually merge in map metadata
        $expected = [
            ...$expectedBase,
            'map' => [
                ...$mapMeta->toArray(),
                ...$map->toArray(),
            ],
        ];

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('include')]
    public function test_include_players_flair_adds_avatar_and_banner_urls(): void
    {
        $player = User::factory()
            ->withOak('test_oak_123')
            ->cachedFlair()
            ->create();
        $completion = Completion::factory()->create();
        $meta = CompletionMeta::factory()
            ->for($completion)
            ->accepted()
            ->withPlayers([$player])
            ->create();

        NinjaKiwiApiClient::fake([
            'avatar_url' => 'https://example.com/avatar.png',
            'banner_url' => 'https://example.com/banner.png',
        ]);

        $meta->load(['completion.map', 'players', 'lcc', 'acceptedBy']);

        $actual = $this->getJson("/api/completions/{$completion->id}?include=players.flair")
            ->assertStatus(200)
            ->json();

        // Verify players array contains avatar and banner URLs
        $this->assertIsArray($actual['players']);
        $this->assertCount(1, $actual['players']);
        $this->assertEquals('https://example.com/avatar.png', $actual['players'][0]['avatar_url']);
        $this->assertEquals('https://example.com/banner.png', $actual['players'][0]['banner_url']);
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('include')]
    public function test_include_accepted_by_flair_adds_avatar_and_banner_urls(): void
    {
        $accepter = User::factory()
            ->withOak('accepter_oak_456')
            ->cachedFlair(
                'https://example.com/accepter-avatar.png',
                'https://example.com/accepter-banner.png'
            )
            ->create();
        $completion = Completion::factory()->create();
        $meta = CompletionMeta::factory()
            ->for($completion)
            ->accepted($accepter->discord_id)
            ->create();

        NinjaKiwiApiClient::fake([
            'avatar_url' => 'https://example.com/accepter-avatar.png',
            'banner_url' => 'https://example.com/accepter-banner.png',
        ]);

        $meta->load(['completion.map', 'players', 'lcc', 'acceptedBy']);

        $actual = $this->getJson("/api/completions/{$completion->id}?include=accepted_by.flair")
            ->assertStatus(200)
            ->json();

        // Verify accepted_by contains avatar and banner URLs
        $this->assertIsArray($actual['accepted_by']);
        $this->assertEquals('https://example.com/accepter-avatar.png', $actual['accepted_by']['avatar_url']);
        $this->assertEquals('https://example.com/accepter-banner.png', $actual['accepted_by']['banner_url']);
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('include')]
    public function test_multiple_include_parameters_work_together(): void
    {
        $player = User::factory()
            ->withOak('player_oak')
            ->cachedFlair()
            ->create();

        $accepter = User::factory()
            ->withOak('accepter_oak')
            ->cachedFlair()
            ->create();
        $completion = Completion::factory()->create();
        $meta = CompletionMeta::factory()
            ->for($completion)
            ->accepted($accepter->discord_id)
            ->withPlayers([$player])
            ->create();

        $map = $completion->map;
        $mapMeta = MapListMeta::with('retroMap.game')
            ->where('code', $map->code)
            ->first();

        $meta->load(['completion.map', 'players', 'lcc', 'acceptedBy']);

        // Setup different fakes for player and accepter
        $callCount = 0;
        NinjaKiwiApiClient::fake([
            'avatar_url' => 'https://example.com/avatar.png',
            'banner_url' => 'https://example.com/banner.png',
        ]);

        $actual = $this->getJson("/api/completions/{$completion->id}?include=map.metadata,players.flair,accepted_by.flair")
            ->assertStatus(200)
            ->json();

        // Verify map metadata is included
        $this->assertArrayHasKey('placement_curver', $actual['map']);
        $this->assertArrayHasKey('difficulty', $actual['map']);

        // Verify players have flair
        $this->assertEquals('https://example.com/avatar.png', $actual['players'][0]['avatar_url']);
        $this->assertEquals('https://example.com/banner.png', $actual['players'][0]['banner_url']);

        // Verify accepted_by has flair
        $this->assertEquals('https://example.com/avatar.png', $actual['accepted_by']['avatar_url']);
        $this->assertEquals('https://example.com/banner.png', $actual['accepted_by']['banner_url']);
    }
}
