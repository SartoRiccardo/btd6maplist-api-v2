<?php

namespace Tests\Feature\MapSubmissions;

use App\Constants\FormatConstants;
use App\Models\Format;
use App\Models\MapSubmission;
use App\Models\User;
use Tests\TestCase;
use PHPUnit\Metadata\Group;

class IndexTest extends TestCase
{
    #[Group('index')]
    #[Group('map_submissions')]
    public function test_index_is_publicly_accessible(): void
    {
        MapSubmission::factory()->count(3)->create();

        $this->getJson('/api/maps/submissions')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    #[Group('index')]
    #[Group('map_submissions')]
    public function test_index_returns_paginated_data(): void
    {
        MapSubmission::factory()->count(15)->create();

        $response = $this->getJson('/api/maps/submissions?page=1&per_page=10');

        $response->assertStatus(200)
            ->assertJson([
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 10,
                    'total' => 15,
                ],
            ]);

        $data = $response->json('data');
        $this->assertCount(10, $data);
    }

    #[Group('index')]
    #[Group('map_submissions')]
    public function test_index_returns_correct_structure(): void
    {
        $submission = MapSubmission::factory()->create();

        $actual = $this->getJson('/api/maps/submissions')
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(1, $actual);
        $expected = MapSubmission::jsonStructure($submission->toArray());
        $expected['submitter'] = [
            'discord_id' => $submission->submitter->discord_id,
            'name' => $submission->submitter->name,
            'is_banned' => $submission->submitter->is_banned,
        ];
        $expected['rejecter'] = null;
        $expected['format'] = Format::jsonStructure($submission->format->toArray());

        $this->assertEquals($expected, $actual[0]);
    }

    #[Group('index')]
    #[Group('map_submissions')]
    public function test_index_filters_by_format_id(): void
    {
        $format1 = Format::find(FormatConstants::MAPLIST);
        $format2 = Format::find(FormatConstants::EXPERT_LIST);

        MapSubmission::factory()->for($format1)->count(3)->create();
        MapSubmission::factory()->for($format2)->count(2)->create();

        $actual = $this->getJson("/api/maps/submissions?format_id={$format1->id}")
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(3, $actual);

        foreach ($actual as $submission) {
            $this->assertEquals($format1->id, $submission['format_id']);
        }
    }

    #[Group('index')]
    #[Group('map_submissions')]
    public function test_index_filters_by_submitter_id(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        MapSubmission::factory()->for($user1, 'submitter')->count(3)->create();
        MapSubmission::factory()->for($user2, 'submitter')->count(2)->create();

        $actual = $this->getJson("/api/maps/submissions?submitter_id={$user1->discord_id}")
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(3, $actual);

        foreach ($actual as $submission) {
            $this->assertEquals($user1->discord_id, $submission['submitter_id']);
        }
    }

    #[Group('index')]
    #[Group('map_submissions')]
    public function test_index_filters_by_both_format_id_and_submitter_id(): void
    {
        $format = Format::find(FormatConstants::MAPLIST);
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        MapSubmission::factory()
            ->for($format)
            ->for($user1, 'submitter')
            ->count(2)
            ->create();

        MapSubmission::factory()
            ->for($format)
            ->for($user2, 'submitter')
            ->count(3)
            ->create();

        MapSubmission::factory()
            ->for(Format::find(FormatConstants::EXPERT_LIST))
            ->for($user1, 'submitter')
            ->count(1)
            ->create();

        $actual = $this->getJson("/api/maps/submissions?format_id={$format->id}&submitter_id={$user1->discord_id}")
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(2, $actual);

        foreach ($actual as $submission) {
            $this->assertEquals($format->id, $submission['format_id']);
            $this->assertEquals($user1->discord_id, $submission['submitter_id']);
        }
    }

    #[Group('index')]
    #[Group('map_submissions')]
    public function test_index_filters_by_status(): void
    {
        $user = User::factory()->create();

        MapSubmission::factory()
            ->for($user, 'submitter')
            ->pending()
            ->count(3)
            ->create();

        MapSubmission::factory()
            ->for($user, 'submitter')
            ->rejected()
            ->count(2)
            ->create();

        MapSubmission::factory()
            ->for($user, 'submitter')
            ->accepted()
            ->count(1)
            ->create();

        // Test pending filter
        $actual = $this->getJson('/api/maps/submissions?status=pending')
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(3, $actual);
        foreach ($actual as $submission) {
            $this->assertEquals('pending', $submission['status']);
        }

        // Test rejected filter
        $actual = $this->getJson('/api/maps/submissions?status=rejected')
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(2, $actual);
        foreach ($actual as $submission) {
            $this->assertEquals('rejected', $submission['status']);
        }

        // Test accepted filter
        $actual = $this->getJson('/api/maps/submissions?status=accepted')
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(1, $actual);
        foreach ($actual as $submission) {
            $this->assertEquals('accepted', $submission['status']);
        }
    }
}
