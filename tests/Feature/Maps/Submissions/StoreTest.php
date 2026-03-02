<?php

namespace Tests\Feature\Maps\Submissions;

use App\Constants\FormatConstants;
use App\Models\Format;
use App\Models\Map;
use App\Models\MapSubmission;
use App\Models\RetroMap;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoreTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /**
     * Test that store submission requires Discord auth.
     */
    public function test_store_submission_requires_discord_auth(): void
    {
        $format = Format::factory()->create(['map_submission_status' => 'open']);
        Map::factory()->create(['code' => 'TEST']);

        $response = $this->postJson('/api/maps/submissions', [
            'code' => 'TEST',
            'format_id' => $format->id,
            'proposed' => 25,
            'completion_proof' => UploadedFile::fake()->image('test.jpg'),
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test that store submission fails if format status is closed.
     */
    public function test_store_submission_fails_if_format_status_is_closed(): void
    {
        $format = Format::factory()->create(['map_submission_status' => 'closed']);
        Map::factory()->create(['code' => 'TEST']);

        $response = $this->actingAs($this->user, 'discord')
            ->postJson('/api/maps/submissions', [
                'code' => 'TEST',
                'format_id' => $format->id,
                'proposed' => 25,
                'completion_proof' => UploadedFile::fake()->image('test.jpg'),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('format_id');
    }

    /**
     * Test that store submission fails if a pending submission already exists.
     */
    public function test_store_submission_fails_if_already_pending(): void
    {
        $format = Format::factory()->create(['map_submission_status' => 'open']);
        $map = Map::factory()->create(['code' => 'TEST']);

        // Create a pending submission
        MapSubmission::factory()->create([
            'code' => 'TEST',
            'format_id' => $format->id,
            'submitter_id' => $this->user->discord_id,
            'rejected_by' => null,
            'accepted_meta_id' => null,
        ]);

        $response = $this->actingAs($this->user, 'discord')
            ->postJson('/api/maps/submissions', [
                'code' => 'TEST',
                'format_id' => $format->id,
                'proposed' => 25,
                'completion_proof' => UploadedFile::fake()->image('test.jpg'),
            ]);

        $response->assertStatus(422);
    }

    /**
     * Test that maplist format checks if map is already in the list.
     */
    public function test_store_submission_fails_if_map_already_active_in_list(): void
    {
        Storage::fake('public');
        
        $format = Format::factory()->create([
            'id' => FormatConstants::MAPLIST,
            'map_submission_status' => 'open'
        ]);
        $map = Map::factory()->create(['code' => 'TEST']);
        
        // Create an active map list meta with placement < map_count
        $map->metas()->create([
            'placement_curver' => 1,
            'placement_allver' => null,
            'difficulty' => 20,
            'created_on' => now(),
            'deleted_on' => null,
        ]);

        $response = $this->actingAs($this->user, 'discord')
            ->postJson('/api/maps/submissions', [
                'code' => 'TEST',
                'format_id' => $format->id,
                'proposed' => 25,
                'completion_proof' => UploadedFile::fake()->image('test.jpg'),
            ]);

        $response->assertStatus(422);
    }

    /**
     * Test that maplist format succeeds if map is dropping off.
     */
    public function test_store_submission_succeeds_if_map_is_dropping_off(): void
    {
        Storage::fake('public');
        
        $format = Format::factory()->create([
            'id' => FormatConstants::MAPLIST,
            'map_submission_status' => 'open'
        ]);
        $map = Map::factory()->create(['code' => 'TEST']);
        
        // Create a meta with placement >= map_count (simulates dropping off)
        $map->metas()->create([
            'placement_curver' => 60,  // Assuming map_count is 50
            'placement_allver' => null,
            'difficulty' => 20,
            'created_on' => now(),
            'deleted_on' => null,
        ]);

        $response = $this->actingAs($this->user, 'discord')
            ->postJson('/api/maps/submissions', [
                'code' => 'TEST',
                'format_id' => $format->id,
                'proposed' => 25,
                'completion_proof' => UploadedFile::fake()->image('test.jpg'),
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['id']);
    }

    /**
     * Test that nostalgia format validates proposed is a valid retro map.
     */
    public function test_store_submission_fails_if_nostalgia_proposed_is_invalid_retro_map(): void
    {
        Storage::fake('public');
        
        $format = Format::factory()->create([
            'id' => FormatConstants::NOSTALGIA_PACK,
            'map_submission_status' => 'open'
        ]);
        $map = Map::factory()->create(['code' => 'TEST']);

        $response = $this->actingAs($this->user, 'discord')
            ->postJson('/api/maps/submissions', [
                'code' => 'TEST',
                'format_id' => $format->id,
                'proposed' => 99999,  // Non-existent retro map ID
                'completion_proof' => UploadedFile::fake()->image('test.jpg'),
            ]);

        $response->assertStatus(422);
    }

    /**
     * Test that submission successfully creates record and uploads image.
     */
    public function test_store_submission_successfully_creates_record_and_uploads_image(): void
    {
        Storage::fake('public');

        $format = Format::factory()->create(['map_submission_status' => 'open']);
        $map = Map::factory()->create(['code' => 'TEST']);

        $response = $this->actingAs($this->user, 'discord')
            ->postJson('/api/maps/submissions', [
                'code' => 'TEST',
                'format_id' => $format->id,
                'proposed' => 25,
                'subm_notes' => 'Great map!',
                'completion_proof' => UploadedFile::fake()->image('test.jpg', 100, 100),
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['id']);

        $submissionId = $response->json('id');
        $submission = MapSubmission::find($submissionId);

        $this->assertNotNull($submission);
        $this->assertEquals('TEST', $submission->code);
        $this->assertEquals($format->id, $submission->format_id);
        $this->assertEquals($this->user->discord_id, $submission->submitter_id);
        $this->assertEquals(25, $submission->proposed);
        $this->assertEquals('Great map!', $submission->subm_notes);
        $this->assertNotNull($submission->completion_proof);
        
        // Verify image was stored
        Storage::disk('public')->assertExists('map_submissions/' . basename($submission->completion_proof));
    }

    /**
     * Test that submission validation fails with invalid format.
     */
    public function test_store_submission_validation_fails_with_invalid_format(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->user, 'discord')
            ->postJson('/api/maps/submissions', [
                'code' => 'TEST',
                'format_id' => 99999,
                'proposed' => 25,
                'completion_proof' => UploadedFile::fake()->image('test.jpg'),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('format_id');
    }

    /**
     * Test that submission validation fails without image.
     */
    public function test_store_submission_validation_fails_without_image(): void
    {
        $format = Format::factory()->create(['map_submission_status' => 'open']);
        Map::factory()->create(['code' => 'TEST']);

        $response = $this->actingAs($this->user, 'discord')
            ->postJson('/api/maps/submissions', [
                'code' => 'TEST',
                'format_id' => $format->id,
                'proposed' => 25,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('completion_proof');
    }

    /**
     * Test that proposed_difficulties validation works if defined.
     */
    public function test_store_submission_validates_proposed_if_format_has_difficulties(): void
    {
        Storage::fake('public');

        $format = Format::factory()->create([
            'map_submission_status' => 'open',
            'proposed_difficulties' => [10, 20, 30, 40],
        ]);
        Map::factory()->create(['code' => 'TEST']);

        $response = $this->actingAs($this->user, 'discord')
            ->postJson('/api/maps/submissions', [
                'code' => 'TEST',
                'format_id' => $format->id,
                'proposed' => 50,  // Not in the list
                'completion_proof' => UploadedFile::fake()->image('test.jpg'),
            ]);

        $response->assertStatus(422);
    }
}
