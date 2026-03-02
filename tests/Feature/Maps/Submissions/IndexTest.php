<?php

namespace Tests\Feature\Maps\Submissions;

use App\Models\Format;
use App\Models\MapSubmission;
use App\Models\User;
use Tests\TestCase;

class IndexTest extends TestCase
{
    /**
     * Test that the index endpoint is publicly accessible.
     */
    public function test_index_is_publicly_accessible(): void
    {
        $response = $this->getJson('/api/maps/submissions');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [],
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
        ]);
    }

    /**
     * Test that the index returns paginated data.
     */
    public function test_index_returns_paginated_data(): void
    {
        // Create test submissions
        MapSubmission::factory()->count(20)->create();

        $response = $this->getJson('/api/maps/submissions?per_page=10');

        $response->assertStatus(200);
        $response->assertJsonCount(10, 'data');
        $response->assertJson([
            'meta' => [
                'current_page' => 1,
                'per_page' => 10,
                'total' => 20,
            ],
        ]);
    }

    /**
     * Test filtering by player_id.
     */
    public function test_index_filters_by_player_id(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        MapSubmission::factory()->create(['submitter_id' => $user1->discord_id]);
        MapSubmission::factory()->count(2)->create(['submitter_id' => $user2->discord_id]);

        $response = $this->getJson("/api/maps/submissions?player_id={$user1->discord_id}");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    /**
     * Test filtering by format_id.
     */
    public function test_index_filters_by_format_id(): void
    {
        $format1 = Format::factory()->create();
        $format2 = Format::factory()->create();

        MapSubmission::factory()->create(['format_id' => $format1->id]);
        MapSubmission::factory()->count(2)->create(['format_id' => $format2->id]);

        $response = $this->getJson("/api/maps/submissions?format_id={$format1->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    /**
     * Test combined filtering.
     */
    public function test_index_filters_by_player_id_and_format_id(): void
    {
        $user = User::factory()->create();
        $format = Format::factory()->create();

        MapSubmission::factory()->create(['submitter_id' => $user->discord_id, 'format_id' => $format->id]);
        MapSubmission::factory()->count(2)->create(['submitter_id' => $user->discord_id]);
        MapSubmission::factory()->count(3)->create(['format_id' => $format->id]);

        $response = $this->getJson("/api/maps/submissions?player_id={$user->discord_id}&format_id={$format->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }
}
