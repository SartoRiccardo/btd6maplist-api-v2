<?php

namespace Tests\Feature\MapSubmissions;

use App\Constants\FormatConstants;
use App\Models\Format;
use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\MapSubmission;
use App\Models\RetroMap;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\TestsDiscordAuthMiddleware;
use PHPUnit\Metadata\Group;

class StoreTest extends TestCase
{
    use TestsDiscordAuthMiddleware;

    protected function endpoint(): string
    {
        return '/api/maps/submissions';
    }

    protected function method(): string
    {
        return 'POST';
    }

    protected function requestData(): array
    {
        return [];
    }

    protected function expectedSuccessStatusCode(): int
    {
        return 201;
    }

    // ========== AUTH TESTS ==========

    #[Group('store')]
    #[Group('map_submissions')]
    public function test_store_inherits_discord_auth_middleware(): void
    {
        $this->test_auth_required();
    }

    // ========== PERMISSION TESTS ==========

    #[Group('store')]
    #[Group('map_submissions')]
    public function test_store_fails_without_create_map_submission_permission(): void
    {
        $user = $this->createUserWithPermissions([]);
        $map = Map::factory()->create();
        $format = Format::find(FormatConstants::MAPLIST);

        Storage::fake('public');

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps/submissions', [
                'code' => $map->code,
                'format_id' => $format->id,
                'proposed' => 1,
                'completion_proof' => UploadedFile::fake()->image('proof.jpg'),
            ])
            ->assertStatus(422)
            ->assertJson(['message' => 'You do not have permission to submit maps for this format.']);
    }

    #[Group('store')]
    #[Group('map_submissions')]
    public function test_store_succeeds_with_create_map_submission_permission(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['create:map_submission']]);
        $map = Map::factory()->create();
        $format = Format::find(FormatConstants::MAPLIST);

        Storage::fake('public');

        $submissionId = $this->actingAs($user, 'discord')
            ->postJson('/api/maps/submissions', [
                'code' => $map->code,
                'format_id' => $format->id,
                'proposed' => 1,
                'completion_proof' => UploadedFile::fake()->image('proof.jpg'),
            ])
            ->assertStatus(201)
            ->json('id');

        // Verify submission was created using GET (not database query)
        $actual = $this->getJson("/api/maps/submissions/{$submissionId}")
            ->assertStatus(200)
            ->json();

        $expected = MapSubmission::jsonStructure([
            'code' => $map->code,
            'format_id' => $format->id,
            'proposed' => 1,
            'submitter_id' => $user->discord_id,
            'rejected_by' => null,
            'accepted_meta_id' => null,
        ]);

        $this->assertEquals($expected, $actual);
    }

    // ========== FORMAT STATUS TESTS ==========

    #[Group('store')]
    #[Group('map_submissions')]
    public function test_store_fails_if_format_map_submission_status_is_closed(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['create:map_submission']]);
        $map = Map::factory()->create();
        $format = Format::find(FormatConstants::MAPLIST);
        $format->map_submission_status = 'closed';
        $format->save();

        Storage::fake('public');

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps/submissions', [
                'code' => $map->code,
                'format_id' => $format->id,
                'proposed' => 1,
                'completion_proof' => UploadedFile::fake()->image('proof.jpg'),
            ])
            ->assertStatus(422)
            ->assertJson(['message' => 'Map submissions are closed for this format.']);
    }

    // ========== PENDING SUBMISSION TESTS ==========

    #[Group('store')]
    #[Group('map_submissions')]
    public function test_store_fails_if_pending_submission_exists(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['create:map_submission']]);
        $map = Map::factory()->create();
        $format = Format::find(FormatConstants::MAPLIST);

        // Create existing pending submission
        MapSubmission::factory()
            ->for($map)
            ->for($format)
            ->pending()
            ->create();

        Storage::fake('public');

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps/submissions', [
                'code' => $map->code,
                'format_id' => $format->id,
                'proposed' => 1,
                'completion_proof' => UploadedFile::fake()->image('proof.jpg'),
            ])
            ->assertStatus(422)
            ->assertJson(['message' => 'This map already has a pending submission for this format.']);
    }

    // ========== MAPLIST VALIDATION TESTS ==========

    #[Group('store')]
    #[Group('map_submissions')]
    public function test_store_fails_if_map_already_in_maplist(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['create:map_submission']]);
        $map = Map::factory()->create();

        // Create map in list (placement within map_count)
        MapListMeta::factory()
            ->for($map)
            ->create([
                'placement_curver' => 5,
            ]);

        $format = Format::find(FormatConstants::MAPLIST);

        Storage::fake('public');

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps/submissions', [
                'code' => $map->code,
                'format_id' => $format->id,
                'proposed' => 1,
                'completion_proof' => UploadedFile::fake()->image('proof.jpg'),
            ])
            ->assertStatus(422)
            ->assertJson(['message' => 'This map is already in the list.']);
    }

    #[Group('store')]
    #[Group('map_submissions')]
    public function test_store_fails_if_map_is_in_list_for_expert_list(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::EXPERT_LIST => ['create:map_submission']]);
        $map = Map::factory()->create();

        // Create map in list with difficulty
        MapListMeta::factory()
            ->for($map)
            ->create([
                'difficulty' => 1,
            ]);

        $format = Format::find(FormatConstants::EXPERT_LIST);

        Storage::fake('public');

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps/submissions', [
                'code' => $map->code,
                'format_id' => $format->id,
                'proposed' => 1,
                'completion_proof' => UploadedFile::fake()->image('proof.jpg'),
            ])
            ->assertStatus(422)
            ->assertJson(['message' => 'This map is already in the list.']);
    }

    #[Group('store')]
    #[Group('map_submissions')]
    public function test_store_fails_if_map_is_in_list_for_best_of_the_best(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::BEST_OF_THE_BEST => ['create:map_submission']]);
        $map = Map::factory()->create();

        // Create map in list with botb_difficulty
        MapListMeta::factory()
            ->for($map)
            ->create([
                'botb_difficulty' => 1,
            ]);

        $format = Format::find(FormatConstants::BEST_OF_THE_BEST);

        Storage::fake('public');

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps/submissions', [
                'code' => $map->code,
                'format_id' => $format->id,
                'proposed' => 1,
                'completion_proof' => UploadedFile::fake()->image('proof.jpg'),
            ])
            ->assertStatus(422)
            ->assertJson(['message' => 'This map is already in the list.']);
    }

    #[Group('store')]
    #[Group('map_submissions')]
    public function test_store_fails_if_map_is_in_list_for_nostalgia_pack(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::NOSTALGIA_PACK => ['create:map_submission']]);
        $map = Map::factory()->create();

        // Create map in list with remake_of
        MapListMeta::factory()
            ->for($map)
            ->create([
                'remake_of' => RetroMap::factory()->create()->id,
            ]);

        $format = Format::find(FormatConstants::NOSTALGIA_PACK);

        Storage::fake('public');

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps/submissions', [
                'code' => $map->code,
                'format_id' => $format->id,
                'proposed' => 1,
                'completion_proof' => UploadedFile::fake()->image('proof.jpg'),
            ])
            ->assertStatus(422)
            ->assertJson(['message' => 'This map is already in the list.']);
    }

    #[Group('store')]
    #[Group('map_submissions')]
    public function test_store_succeeds_if_map_is_dropping_off_list(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['create:map_submission']]);
        $map = Map::factory()->create();

        // Create map with placement beyond map_count (50)
        MapListMeta::factory()
            ->for($map)
            ->create([
                'placement_curver' => 60,
            ]);

        $format = Format::find(FormatConstants::MAPLIST);

        Storage::fake('public');

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps/submissions', [
                'code' => $map->code,
                'format_id' => $format->id,
                'proposed' => 1,
                'completion_proof' => UploadedFile::fake()->image('proof.jpg'),
            ])
            ->assertStatus(201)
            ->assertJsonStructure([
                'id',
            ]);
    }

    #[Group('store')]
    #[Group('map_submissions')]
    public function test_store_succeeds_if_map_not_in_list(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::EXPERT_LIST => ['create:map_submission']]);
        $map = Map::factory()->create();

        // Map has no meta (not in any list)
        $format = Format::find(FormatConstants::EXPERT_LIST);

        Storage::fake('public');

        $submissionId = $this->actingAs($user, 'discord')
            ->postJson('/api/maps/submissions', [
                'code' => $map->code,
                'format_id' => $format->id,
                'proposed' => 1,
                'completion_proof' => UploadedFile::fake()->image('proof.jpg'),
            ])
            ->assertStatus(201)
            ->json('id');

        // Verify submission was created
        $this->assertNotNull($submissionId);
    }

    // ========== IMAGE UPLOAD TESTS ==========

    #[Group('store')]
    #[Group('map_submissions')]
    public function test_store_validates_image_upload(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['create:map_submission']]);
        $map = Map::factory()->create();
        $format = Format::find(FormatConstants::MAPLIST);

        Storage::fake('public');

        // Missing file
        $this->actingAs($user, 'discord')
            ->postJson('/api/maps/submissions', [
                'code' => $map->code,
                'format_id' => $format->id,
                'proposed' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['completion_proof']);
    }

    #[Group('store')]
    #[Group('map_submissions')]
    public function test_store_saves_image_to_storage(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['create:map_submission']]);
        $map = Map::factory()->create();
        $format = Format::find(FormatConstants::MAPLIST);

        Storage::fake('public');
        $file = UploadedFile::fake()->image('proof.jpg');

        $submissionId = $this->actingAs($user, 'discord')
            ->postJson('/api/maps/submissions', [
                'code' => $map->code,
                'format_id' => $format->id,
                'proposed' => 1,
                'completion_proof' => $file,
            ])
            ->assertStatus(201)
            ->json('id');

        // Verify image was stored using GET (not database query)
        $actual = $this->getJson("/api/maps/submissions/{$submissionId}")
            ->assertStatus(200)
            ->json();

        $this->assertNotNull($actual['completion_proof']);
        Storage::disk('public')->assertExists($actual['completion_proof']);
    }
}
