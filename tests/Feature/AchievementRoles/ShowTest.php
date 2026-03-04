<?php

namespace Tests\Feature\AchievementRoles;

use App\Models\AchievementRole;
use App\Models\DiscordRole;
use Tests\TestCase;

class ShowTest extends TestCase
{

    #[Group('get')]
    #[Group('achievement_roles')]
    public function test_show_returns_achievement_role_by_id_with_discord_roles(): void
    {
        $role = AchievementRole::factory()->create(['id' => 100]);
        DiscordRole::factory()->forAchievementRole($role)->create(['guild_id' => '123', 'role_id' => '456']);

        $actual = $this->getJson('/api/roles/achievement/100')
            ->assertStatus(200)
            ->json();

        $expected = AchievementRole::jsonStructure($role->toArray());
        $expected['discord_roles'] = [
            [
                'guild_id' => '123',
                'role_id' => '456',
            ],
        ];

        $this->assertEquals($expected, $this->except($actual, ['id']));
    }

    #[Group('get')]
    #[Group('achievement_roles')]
    public function test_show_returns_404_if_not_found(): void
    {
        $this->getJson('/api/roles/achievement/999999')
            ->assertStatus(404)
            ->assertJson(['message' => 'Not Found']);
    }
}
