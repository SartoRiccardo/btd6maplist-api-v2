<?php

namespace Tests\Feature\Maps\Submissions;

use App\Models\MapSubmission;
use Tests\TestCase;

class ShowTest extends TestCase
{
    /**
     * Test that the show endpoint is publicly accessible.
     */
    public function test_show_is_publicly_accessible(): void
    {
        $submission = MapSubmission::factory()->create();

        $response = $this->getJson("/api/maps/submissions/{$submission->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'id',
            'code',
            'submitter_id',
            'format_id',
            'proposed',
            'completion_proof',
        ]);
    }

    /**
     * Test that show returns 404 for non-existent submission.
     */
    public function test_show_returns_404_if_not_found(): void
    {
        $response = $this->getJson('/api/maps/submissions/99999');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Submission not found']);
    }

    /**
     * Test that the submission data is correctly returned.
     */
    public function test_show_returns_correct_submission_data(): void
    {
        $submission = MapSubmission::factory()->create([
            'code' => 'TEST123',
            'proposed' => 42,
            'subm_notes' => 'Test notes',
        ]);

        $response = $this->getJson("/api/maps/submissions/{$submission->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $submission->id,
            'code' => 'TEST123',
            'proposed' => 42,
            'subm_notes' => 'Test notes',
        ]);
    }

    /**
     * Test that hidden fields are not returned.
     */
    public function test_show_does_not_return_hidden_fields(): void
    {
        $submission = MapSubmission::factory()->create([
            'wh_data' => 'secret webhook data',
            'wh_msg_id' => 12345,
        ]);

        $response = $this->getJson("/api/maps/submissions/{$submission->id}");

        $response->assertStatus(200);
        $response->assertJsonMissing(['wh_data' => 'secret webhook data']);
        $response->assertJsonMissing(['wh_msg_id' => 12345]);
    }
}
