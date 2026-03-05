<?php

namespace Tests\Feature\Search;

use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Group('get')]
    #[Group('search')]
    public function test_search_returns_both_maps_and_users_by_default(): void
    {
        User::factory()->create(['name' => 'TestUser']);
        Map::factory()->create(['name' => 'TestMap', 'code' => 'TSTMAP01']);
        MapListMeta::factory()->create(['code' => 'TSTMAP01', 'created_on' => now()]);

        $results = $this->getJson('/api/search?q=test')
            ->assertStatus(200)
            ->assertJsonCount(2)
            ->json();

        $types = collect($results)->pluck('type');

        $this->assertTrue($types->contains('user'));
        $this->assertTrue($types->contains('map'));
    }

    #[Group('get')]
    #[Group('search')]
    #[Group('response')]
    public function test_search_includes_user_structure(): void
    {
        $user = User::factory()->create(['name' => 'TestUser']);

        $actual = $this->getJson('/api/search?q=testuser&entities=users')
            ->assertStatus(200)
            ->json()[0];

        $expected = [
            'type' => 'user',
            'result' => User::jsonStructure($user->toArray(), exclude: ['roles']),
        ];

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('search')]
    public function test_search_filters_by_entities_parameter(): void
    {
        User::factory()->create(['name' => 'TestUser']);
        Map::factory()->create(['name' => 'TestMap', 'code' => 'TSTMAP01']);
        MapListMeta::factory()->create(['code' => 'TSTMAP01', 'created_on' => now()]);

        $results = $this->getJson('/api/search?q=test&entities=users')
            ->assertStatus(200)
            ->assertJsonCount(1)
            ->json();

        $this->assertEquals('user', $results[0]['type']);
    }

    #[Group('get')]
    #[Group('search')]
    public function test_search_sorts_by_similarity_descending(): void
    {
        // Create users with decreasing similarity to "test"
        User::factory()->create(['name' => 'test']);          // Exact match - highest similarity
        User::factory()->create(['name' => 'testing']);       // High similarity (shares "test" prefix)
        User::factory()->create(['name' => 'atestuser']);     // Lower similarity (contains "test")

        $results = $this->getJson('/api/search?q=test&entities=users')
            ->assertStatus(200)
            ->json();

        $names = collect($results)->pluck('result.name');

        $this->assertCount(3, $names);
        // Verify order: exact match should be first, then "testing", then "atestuser"
        $this->assertEquals('test', $names[0]);
        $this->assertEquals('testing', $names[1]);
        $this->assertEquals('atestuser', $names[2]);
    }

    #[Group('get')]
    #[Group('search')]
    public function test_search_excludes_low_similarity_results(): void
    {
        User::factory()->create(['name' => 'abcdefghijk']); // Very different from 'xyz'

        $this->getJson('/api/search?q=xyz&entities=users')
            ->assertStatus(200)
            ->assertJsonCount(0);
    }

    #[Group('get')]
    #[Group('search')]
    public function test_search_respects_limit_parameter(): void
    {
        User::factory()->create(['name' => 'TestUser1']);
        User::factory()->create(['name' => 'TestUser2']);
        User::factory()->create(['name' => 'TestUser3']);

        $this->getJson('/api/search?q=test&entities=users&limit=2')
            ->assertStatus(200)
            ->assertJsonCount(2);
    }

    #[Group('get')]
    #[Group('search')]
    public function test_search_includes_active_map_metadata(): void
    {
        $map = Map::factory()->create([
            'name' => 'Infernal',
            'code' => 'INFRL',
        ]);

        $meta = MapListMeta::factory()->create([
            'code' => 'INFRL',
            'placement_curver' => 1,
            'placement_allver' => null,
            'difficulty' => 5,
            'botb_difficulty' => null,
            'remake_of' => null,
            'optimal_heros' => [],
            'created_on' => now(),
        ]);

        $actual = $this->getJson('/api/search?q=infernal&entities=maps')
            ->assertStatus(200)
            ->json()[0];

        $expected = [
            'type' => 'map',
            'result' => Map::jsonStructure([
                ...$meta->toArray(),
                ...$map->toArray(),
            ], strict: false),
        ];

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('search')]
    public function test_search_handles_spaces_in_query(): void
    {
        User::factory()->create(['name' => 'Test User']);

        $result = $this->getJson('/api/search?q=' . urlencode('test us') . '&entities=users')
            ->assertStatus(200)
            ->assertJsonCount(1)
            ->json()[0];

        $this->assertEquals('user', $result['type']);
        $this->assertEquals('Test User', $result['result']['name']);
    }

    #[Group('get')]
    #[Group('search')]
    public function test_search_with_no_matches_returns_empty_array(): void
    {
        User::factory()->create(['name' => 'JohnDoe']);

        $this->getJson('/api/search?q=nonexistentname12345&entities=users')
            ->assertStatus(200)
            ->assertJsonCount(0);
    }
}
