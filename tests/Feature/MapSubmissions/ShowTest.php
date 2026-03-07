<?php

namespace Tests\Feature\MapSubmissions;

use App\Models\Format;
use App\Models\MapSubmission;
use App\Models\User;
use Tests\TestCase;
use PHPUnit\Metadata\Group;

class ShowTest extends TestCase
{
    #[Group('show')]
    #[Group('map_submissions')]
    public function test_show_is_publicly_accessible(): void
    {
        $submission = MapSubmission::factory()->create();

        $actual = $this->getJson("/api/maps/submissions/{$submission->id}")
            ->assertStatus(200)
            ->json();

        $expected = MapSubmission::jsonStructure($submission->toArray());
        $expected['submitter'] = [
            'discord_id' => $submission->submitter->discord_id,
            'name' => $submission->submitter->name,
            'is_banned' => $submission->submitter->is_banned,
        ];
        $expected['rejecter'] = null;
        $expected['format'] = Format::jsonStructure($submission->format->toArray());
        $expected['accepted_meta'] = null;

        $this->assertEquals($expected, $actual);
    }

    #[Group('show')]
    #[Group('map_submissions')]
    public function test_show_includes_status_attribute(): void
    {
        $pending = MapSubmission::factory()->pending()->create();
        $rejected = MapSubmission::factory()->rejected()->create();
        $accepted = MapSubmission::factory()->accepted()->create();

        // Test pending status
        $actual = $this->getJson("/api/maps/submissions/{$pending->id}")
            ->assertStatus(200)
            ->json();

        $this->assertEquals('pending', $actual['status']);

        // Test rejected status
        $actual = $this->getJson("/api/maps/submissions/{$rejected->id}")
            ->assertStatus(200)
            ->json();

        $this->assertEquals('rejected', $actual['status']);

        // Test accepted status
        $actual = $this->getJson("/api/maps/submissions/{$accepted->id}")
            ->assertStatus(200)
            ->json();

        $this->assertEquals('accepted', $actual['status']);
    }

    #[Group('show')]
    #[Group('map_submissions')]
    public function test_show_returns_404_if_not_found(): void
    {
        $this->getJson('/api/maps/submissions/999999')
            ->assertStatus(404)
            ->assertJson(['message' => 'Not Found']);
    }

    #[Group('show')]
    #[Group('map_submissions')]
    public function test_show_includes_submitter_relationship(): void
    {
        $submission = MapSubmission::factory()->create();

        $actual = $this->getJson("/api/maps/submissions/{$submission->id}")
            ->assertStatus(200)
            ->json();

        $this->assertArrayHasKey('submitter', $actual);
        $this->assertEquals($submission->submitter->discord_id, $actual['submitter']['discord_id']);
    }

    #[Group('show')]
    #[Group('map_submissions')]
    public function test_show_includes_format_relationship(): void
    {
        $submission = MapSubmission::factory()->create();

        $actual = $this->getJson("/api/maps/submissions/{$submission->id}")
            ->assertStatus(200)
            ->json();

        $this->assertArrayHasKey('format', $actual);
        $this->assertEquals($submission->format->id, $actual['format']['id']);
    }
}
