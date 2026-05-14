<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Tests\TestCase;
use Tests\Traits\TestsDiscordAuthMiddleware;

class ListUsersTest extends TestCase
{
    use TestsDiscordAuthMiddleware;

    // GET /api/users — Paginated user list. Requires global list:users permission.
    // Response: { data, total, page, per_page } (NOT the standard meta wrapper)

    protected function endpoint(): string { return '/api/users'; }
    protected function method(): string { return 'GET'; }

    private function actorWithPermission(): User
    {
        return $this->createUserWithPermissions([null => ['list:users']]);
    }

    public function test_returns_paginated_user_list_with_200(): void
    {
        $actor = $this->actorWithPermission();
        User::factory()->count(3)->create();

        $actual = $this->actingAs($actor, 'discord')
            ->getJson('/api/users')
            ->assertStatus(200)
            ->json();

        $this->assertArrayHasKey('data', $actual);
        $this->assertArrayHasKey('total', $actual);
        $this->assertArrayHasKey('page', $actual);
        $this->assertArrayHasKey('per_page', $actual);
        $this->assertIsArray($actual['data']);
    }

    public function test_results_sorted_alphabetically_without_search(): void
    {
        $actor = $this->actorWithPermission();
        User::factory()->create(['name' => 'Charlie']);
        User::factory()->create(['name' => 'Alice']);
        User::factory()->create(['name' => 'Bob']);

        $actual = $this->actingAs($actor, 'discord')
            ->getJson('/api/users')
            ->assertStatus(200)
            ->json('data');

        // Filter to known names only — the actor user has a random faker name that lands
        // in the response and breaks PHP sort() vs Postgres collation when it starts lowercase.
        $known = ['Alice', 'Bob', 'Charlie'];
        $filtered = collect($actual)
            ->pluck('name')
            ->filter(fn($name) => in_array($name, $known))
            ->values()
            ->toArray();

        $this->assertEquals(['Alice', 'Bob', 'Charlie'], $filtered);
    }

    public function test_search_returns_trigram_similar_names(): void
    {
        $actor = $this->actorWithPermission();
        User::factory()->create(['name' => 'CyberNinja']);
        User::factory()->create(['name' => 'TotallyUnrelated']);

        $actual = $this->actingAs($actor, 'discord')
            ->getJson('/api/users?search=CyberNinja')
            ->assertStatus(200)
            ->json('data');

        $names = collect($actual)->pluck('name')->toArray();
        $this->assertContains('CyberNinja', $names);
        $this->assertNotContains('TotallyUnrelated', $names);
    }

    public function test_include_flair_appends_avatar_url_and_banner_url(): void
    {
        $actor = $this->actorWithPermission();
        User::factory()->create([
            'cached_avatar_url' => 'https://example.com/av.png',
            'cached_banner_url' => 'https://example.com/bn.png',
            'ninjakiwi_cache_expire' => now()->addHour(),
        ]);

        $actual = $this->actingAs($actor, 'discord')
            ->getJson('/api/users?include=flair')
            ->assertStatus(200)
            ->json('data');

        foreach ($actual as $u) {
            $this->assertArrayHasKey('avatar_url', $u);
            $this->assertArrayHasKey('banner_url', $u);
        }
    }

    public function test_no_list_users_permission_returns_403(): void
    {
        $actor = $this->createUserWithPermissions([]);

        $this->actingAs($actor, 'discord')
            ->getJson('/api/users')
            ->assertStatus(403);
    }

    public function test_search_with_zero_matches_returns_empty_data_and_total_0(): void
    {
        $actor = $this->actorWithPermission();

        $actual = $this->actingAs($actor, 'discord')
            ->getJson('/api/users?search=xyznotexist99999')
            ->assertStatus(200)
            ->json();

        $this->assertEmpty($actual['data']);
        $this->assertEquals(0, $actual['total']);
    }

    public function test_user_below_similarity_threshold_excluded(): void
    {
        $actor = $this->actorWithPermission();
        User::factory()->create(['name' => 'CompletelyDifferentName']);

        $actual = $this->actingAs($actor, 'discord')
            ->getJson('/api/users?search=xyz')
            ->assertStatus(200)
            ->json('data');

        $names = collect($actual)->pluck('name')->toArray();
        $this->assertNotContains('CompletelyDifferentName', $names);
    }

    public function test_per_page_clamped_to_100(): void
    {
        $actor = $this->actorWithPermission();

        // No 422 — per_page is silently clamped to 100
        $actual = $this->actingAs($actor, 'discord')
            ->getJson('/api/users?per_page=200')
            ->assertStatus(200)
            ->json('per_page');

        $this->assertEquals(100, $actual);
    }

    public function test_page_2_returns_non_overlapping_results(): void
    {
        $actor = $this->actorWithPermission();
        User::factory()->count(25)->create();

        $page1 = $this->actingAs($actor, 'discord')
            ->getJson('/api/users?per_page=20&page=1')
            ->assertStatus(200)
            ->json('data');

        $page2 = $this->actingAs($actor, 'discord')
            ->getJson('/api/users?per_page=20&page=2')
            ->assertStatus(200)
            ->json('data');

        $ids1 = collect($page1)->pluck('discord_id')->toArray();
        $ids2 = collect($page2)->pluck('discord_id')->toArray();
        $this->assertEmpty(array_intersect($ids1, $ids2));
    }

    public function test_include_flair_user_no_nk_oak_returns_null_for_both_urls_not_missing(): void
    {
        $actor = $this->actorWithPermission();
        User::factory()->create(['nk_oak' => null]);

        $actual = $this->actingAs($actor, 'discord')
            ->getJson('/api/users?include=flair')
            ->assertStatus(200)
            ->json('data');

        foreach ($actual as $u) {
            $this->assertArrayHasKey('avatar_url', $u);
            $this->assertArrayHasKey('banner_url', $u);
            // nk_oak is hidden; check all users have the keys, at least one has null values
        }
        $nullFlair = collect($actual)->first(fn($u) => $u['avatar_url'] === null);
        $this->assertNotNull($nullFlair);
    }

    public function test_simil_internal_field_never_leaks_into_response(): void
    {
        $actor = $this->actorWithPermission();
        User::factory()->create(['name' => 'SearchableUser']);

        $actual = $this->actingAs($actor, 'discord')
            ->getJson('/api/users?search=SearchableUser')
            ->assertStatus(200)
            ->json('data');

        foreach ($actual as $u) {
            $this->assertArrayNotHasKey('simil', $u);
        }
    }

    public function test_unknown_include_value_ignored_no_error(): void
    {
        $actor = $this->actorWithPermission();

        $this->actingAs($actor, 'discord')
            ->getJson('/api/users?include=garbage')
            ->assertStatus(200);
    }
}
