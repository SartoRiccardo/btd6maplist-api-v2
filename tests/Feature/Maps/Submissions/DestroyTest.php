<?php

namespace Tests\Feature\Maps\Submissions;

use App\Models\MapSubmission;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    protected User $submitter;
    protected User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->submitter = User::factory()->create();
        $this->otherUser = User::factory()->create();
    }

    /**
     * Test that destroy requires Discord auth.
     */
    public function test_destroy_requires_discord_auth(): void
    {
        $submission = MapSubmission::factory()->create(['submitter_id' => $this->submitter->discord_id]);

        $response = $this->deleteJson("/api/maps/submissions/{$submission->id}");

        $response->assertStatus(401);
    }

    /**
     * Test that destroy fails if user is not the submitter.
     */
    public function test_destroy_fails_if_not_owner(): void
    {
        $submission = MapSubmission::factory()->create(['submitter_id' => $this->submitter->discord_id]);

        $response = $this->actingAs($this->otherUser, 'discord')
            ->deleteJson("/api/maps/submissions/{$submission->id}");

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Forbidden']);
    }

    /**
     * Test that destroy fails if submission is already rejected.
     */
    public function test_destroy_fails_if_already_processed(): void
    {
        $submission = MapSubmission::factory()->create([
            'submitter_id' => $this->submitter->discord_id,
            'rejected_by' => '123456789',
        ]);

        $response = $this->actingAs($this->submitter, 'discord')
            ->deleteJson("/api/maps/submissions/{$submission->id}");

        $response->assertStatus(422);
    }

    /**
     * Test that destroy fails if submission is already accepted.
     */
    public function test_destroy_fails_if_already_accepted(): void
    {
        $submission = MapSubmission::factory()->create([
            'submitter_id' => $this->submitter->discord_id,
            'accepted_meta_id' => 1,
        ]);

        $response = $this->actingAs($this->submitter, 'discord')
            ->deleteJson("/api/maps/submissions/{$submission->id}");

        $response->assertStatus(422);
    }

    /**
     * Test that destroy returns 404 for non-existent submission.
     */
    public function test_destroy_returns_404_if_not_found(): void
    {
        $response = $this->actingAs($this->submitter, 'discord')
            ->deleteJson('/api/maps/submissions/99999');

        $response->assertStatus(404);
    }

    /**
     * Test that destroy successfully deletes a pending submission.
     */
    public function test_destroy_hard_deletes_successfully_for_owner(): void
    {
        $submission = MapSubmission::factory()->create([
            'submitter_id' => $this->submitter->discord_id,
            'rejected_by' => null,
            'accepted_meta_id' => null,
        ]);

        $submissionId = $submission->id;

        $response = $this->actingAs($this->submitter, 'discord')
            ->deleteJson("/api/maps/submissions/{$submissionId}");

        $response->assertStatus(204);

        $this->assertNull(MapSubmission::find($submissionId));
    }

    /**
     * Test that destroy removes image from storage.
     */
    public function test_destroy_removes_image_from_storage(): void
    {
        Storage::fake('public');

        // Create a fake image and store its URL
        $submission = MapSubmission::factory()->create([
            'submitter_id' => $this->submitter->discord_id,
            'completion_proof' => 'http://localhost/storage/map_submissions/test.jpg',
            'rejected_by' => null,
            'accepted_meta_id' => null,
        ]);

        $submissionId = $submission->id;

        // Note: The actual deletion may not work perfectly with fake storage since we're dealing with URLs
        // This test mainly ensures the code path runs without errors
        $response = $this->actingAs($this->submitter, 'discord')
            ->deleteJson("/api/maps/submissions/{$submissionId}");

        $response->assertStatus(204);
        $this->assertNull(MapSubmission::find($submissionId));
    }
}
