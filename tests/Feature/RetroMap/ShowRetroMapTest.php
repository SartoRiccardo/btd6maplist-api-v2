<?php

namespace Tests\Feature\RetroMap;

use App\Models\RetroGame;
use App\Models\RetroMap;
use Tests\TestCase;

class ShowRetroMapTest extends TestCase
{
    #[Group('show')]
    #[Group('retro_maps')]
    public function test_show_returns_exact_structure(): void
    {
        $retroGame = RetroGame::factory()->create();
        $retroMap = RetroMap::factory()->for($retroGame, 'game')->create();

        $actual = $this->getJson("/api/maps/retro/{$retroMap->id}")
            ->assertStatus(200)
            ->json();

        $expected = RetroMap::jsonStructure([
            ...$retroMap->toArray(),
            'game' => $retroGame->toArray(),
        ], exclude: ['deleted_at']);

        $this->assertEquals($expected, $this->except($actual, ['deleted_at']));
    }

    #[Group('show')]
    #[Group('retro_maps')]
    public function test_show_returns_404_if_not_found(): void
    {
        $this->getJson('/api/maps/retro/999999')
            ->assertStatus(404)
            ->assertJson(['message' => 'Not Found']);
    }
}
