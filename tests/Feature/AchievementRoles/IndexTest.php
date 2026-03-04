<?php

namespace Tests\Feature\AchievementRoles;

use App\Constants\FormatConstants;
use App\Models\AchievementRole;
use App\Models\DiscordRole;
use Tests\TestCase;

class IndexTest extends TestCase
{
    #[Group('get')]
    #[Group('achievement_roles')]
    public function test_index_returns_paginated_list(): void
    {
        $roles = AchievementRole::factory()->count(15)->create();
        $roles->each(function ($role) {
            DiscordRole::factory()->forAchievementRole($role)->create();
        });

        $actual = $this->getJson('/api/roles/achievement')
            ->assertStatus(200)
            ->json();

        $this->assertIsArray($actual['data']);
        $this->assertIsArray($actual['meta']);
        $this->assertArrayHasKey('current_page', $actual['meta']);
        $this->assertArrayHasKey('last_page', $actual['meta']);
        $this->assertArrayHasKey('per_page', $actual['meta']);
        $this->assertArrayHasKey('total', $actual['meta']);
        $this->assertCount(15, $actual['data']);
        $this->assertEquals(15, $actual['meta']['total']);
    }

    #[Group('get')]
    #[Group('achievement_roles')]
    public function test_index_structure(): void
    {
        $role = AchievementRole::factory()->create();
        DiscordRole::factory()->forAchievementRole($role)->create(['guild_id' => '123', 'role_id' => '456']);
        DiscordRole::factory()->forAchievementRole($role)->create(['guild_id' => '789', 'role_id' => '789']);

        $actual = $this->getJson('/api/roles/achievement')
            ->assertStatus(200)
            ->json();

        $expected = AchievementRole::jsonStructure($role->toArray());
        $expected['discord_roles'] = [
            ['guild_id' => '123', 'role_id' => '456'],
            ['guild_id' => '789', 'role_id' => '789'],
        ];

        $this->assertEquals($expected, $this->except($actual['data'][0], ['id']));
    }

    #[Group('get')]
    #[Group('achievement_roles')]
    public function test_index_filters_by_format_id(): void
    {
        AchievementRole::factory()->create(['lb_format' => FormatConstants::MAPLIST, 'lb_type' => 'points', 'threshold' => 10]);
        AchievementRole::factory()->create(['lb_format' => FormatConstants::MAPLIST_ALL_VERSIONS, 'lb_type' => 'points', 'threshold' => 10]);
        AchievementRole::factory()->create(['lb_format' => FormatConstants::MAPLIST, 'lb_type' => 'points', 'threshold' => 20]);

        $actual = $this->getJson('/api/roles/achievement?format_id=' . FormatConstants::MAPLIST)
            ->assertStatus(200)
            ->json();

        $this->assertCount(2, $actual['data']);
        $this->assertEquals(FormatConstants::MAPLIST, $actual['data'][0]['lb_format']);
        $this->assertEquals(FormatConstants::MAPLIST, $actual['data'][1]['lb_format']);
    }

    #[Group('get')]
    #[Group('achievement_roles')]
    public function test_index_filters_by_type(): void
    {
        AchievementRole::factory()->create(['lb_format' => FormatConstants::MAPLIST, 'lb_type' => 'black_border', 'threshold' => 10]);
        AchievementRole::factory()->create(['lb_format' => FormatConstants::MAPLIST, 'lb_type' => 'no_geraldo', 'threshold' => 10]);
        AchievementRole::factory()->create(['lb_format' => FormatConstants::MAPLIST, 'lb_type' => 'black_border', 'threshold' => 20]);

        $actual = $this->getJson('/api/roles/achievement?type=black_border')
            ->assertStatus(200)
            ->json();

        $this->assertCount(2, $actual['data']);
        $this->assertEquals('black_border', $actual['data'][0]['lb_type']);
        $this->assertEquals('black_border', $actual['data'][1]['lb_type']);
    }
}
