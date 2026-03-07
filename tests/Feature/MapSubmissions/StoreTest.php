<?php

namespace Tests\Feature\MapSubmissions;

use App\Constants\FormatConstants;
use App\Models\Format;
use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\MapSubmission;
use App\Models\RetroMap;
use App\Services\NinjaKiwi\NinjaKiwiApiClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\TestsDiscordAuthMiddleware;
use PHPUnit\Metadata\Group;

class StoreTest extends TestCase
{
    use TestsDiscordAuthMiddleware;

    protected function tearDown(): void
    {
        // Clean up NK API fake
        NinjaKiwiApiClient::clearFake();
        parent::tearDown();
    }

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
        return [
            'code' => 'TestCode1',
            'format_id' => FormatConstants::MAPLIST,
            'proposed' => 0, // "Top 3"
            'completion_proof' => UploadedFile::fake()->image('proof.jpg'),
        ];
    }

    protected function expectedSuccessStatusCode(): int
    {
        return 201;
    }

    // ========== PERMISSION TESTS ==========

    #[Group('store')]
    #[Group('map_submissions')]
    public function test_store_fails_if_map_does_not_exist(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['create:map_submission']]);
        $format = Format::find(FormatConstants::MAPLIST);
        $format->map_submission_status = 'open';
        $format->save();

        // Mock NK API to return map does not exist
        NinjaKiwiApiClient::fakeMapExists(['NoExist' => false]);

        Storage::fake('public');

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps/submissions', [
                'code' => 'NoExist',
                'format_id' => $format->id,
                'proposed' => 1,
                'completion_proof' => UploadedFile::fake()->image('proof.jpg'),
            ])
            ->assertStatus(422)
            ->assertJson(['message' => 'This map code does not exist.']);
    }

    #[Group('store')]
    #[Group('map_submissions')]
    public function test_store_fails_without_create_map_submission_permission(): void
    {
        $user = $this->createUserWithPermissions([]);
        $map = Map::factory()->create();
        $format = Format::find(FormatConstants::MAPLIST);
        $format->map_submission_status = 'open';
        $format->save();

        // Mock NK API to return map exists
        NinjaKiwiApiClient::fakeMapExists([$map->code => true]);

        Storage::fake('public');

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps/submissions', [
                'code' => $map->code,
                'format_id' => $format->id,
                'proposed' => 0,
                'completion_proof' => UploadedFile::fake()->image('proof.jpg'),
            ])
            ->assertStatus(422)
            ->assertJson(['message' => 'You do not have permission to submit maps for this format.']);
    }

    #[Group('store')]
    #[Group('map_submissions')]
    public function test_store_succeeds_with_create_map_submission_permission(): void
    {
        // Given
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['create:map_submission']]);
        $map = Map::factory()->create();
        $format = Format::find(FormatConstants::MAPLIST);
        $format->map_submission_status = 'open';
        $format->save();

        // Mock NK API to return map exists
        NinjaKiwiApiClient::fakeMapExists([$map->code => true]);

        Storage::fake('public');

        // When
        $submissionId = $this->actingAs($user, 'discord')
            ->postJson('/api/maps/submissions', [
                'code' => $map->code,
                'format_id' => $format->id,
                'proposed' => 0,
                'completion_proof' => UploadedFile::fake()->image('proof.jpg'),
            ])
            ->assertStatus(201)
            ->json('id');

        // Then
        $actual = $this->getJson("/api/maps/submissions/{$submissionId}")
            ->assertStatus(200)
            ->json();

        // Verify key fields match (relationships are loaded separately)
        $this->assertEquals($map->code, $actual['code']);
        $this->assertEquals($format->id, $actual['format_id']);
        $this->assertEquals(0, $actual['proposed']);
        $this->assertEquals($user->discord_id, $actual['submitter_id']);
        $this->assertNull($actual['rejected_by']);
        $this->assertEquals('pending', $actual['status']);
        $this->assertNotNull($actual['id']);
        $this->assertNotNull($actual['created_on']);
        $this->assertNotNull($actual['completion_proof']);
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

        // Mock NK API to return map exists
        NinjaKiwiApiClient::fakeMapExists([$map->code => true]);

        Storage::fake('public');

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps/submissions', [
                'code' => $map->code,
                'format_id' => $format->id,
                'proposed' => 0,
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
        $format->map_submission_status = 'open';
        $format->save();

        // Mock NK API to return map exists
        NinjaKiwiApiClient::fakeMapExists([$map->code => true]);

        // Create existing pending submission
        MapSubmission::factory()
            ->pending()
            ->create([
                'code' => $map->code,
                'format_id' => $format->id,
            ]);

        Storage::fake('public');

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps/submissions', [
                'code' => $map->code,
                'format_id' => $format->id,
                'proposed' => 0,
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
        $format = Format::find(FormatConstants::MAPLIST);
        $format->map_submission_status = 'open';
        $format->save();

        // Mock NK API to return map exists
        NinjaKiwiApiClient::fakeMapExists([$map->code => true]);

        // Create map in list (placement within map_count)
        MapListMeta::factory()
            ->for($map)
            ->create([
                'placement_curver' => 5,
            ]);

        Storage::fake('public');

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps/submissions', [
                'code' => $map->code,
                'format_id' => $format->id,
                'proposed' => 0,
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
        $format = Format::find(FormatConstants::EXPERT_LIST);
        $format->map_submission_status = 'open';
        $format->save();

        // Mock NK API to return map exists
        NinjaKiwiApiClient::fakeMapExists([$map->code => true]);

        // Create map in list with difficulty
        MapListMeta::factory()
            ->for($map)
            ->create([
                'difficulty' => 0, // "Casual Expert"
            ]);

        Storage::fake('public');

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps/submissions', [
                'code' => $map->code,
                'format_id' => $format->id,
                'proposed' => 0,
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
        $format = Format::find(FormatConstants::BEST_OF_THE_BEST);
        $format->map_submission_status = 'open';
        $format->save();

        // Mock NK API to return map exists
        NinjaKiwiApiClient::fakeMapExists([$map->code => true]);

        // Create map in list with botb_difficulty
        MapListMeta::factory()
            ->for($map)
            ->create([
                'botb_difficulty' => 0, // "Beginner"
            ]);

        Storage::fake('public');

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps/submissions', [
                'code' => $map->code,
                'format_id' => $format->id,
                'proposed' => 0,
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
        $format = Format::find(FormatConstants::NOSTALGIA_PACK);
        $format->map_submission_status = 'open';
        $format->save();

        // Mock NK API to return map exists
        NinjaKiwiApiClient::fakeMapExists([$map->code => true]);

        // Create retro map
        $retroMap = RetroMap::factory()->create();

        // Create map in list with remake_of
        MapListMeta::factory()
            ->for($map)
            ->create([
                'remake_of' => $retroMap->id,
            ]);

        Storage::fake('public');

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps/submissions', [
                'code' => $map->code,
                'format_id' => $format->id,
                'proposed' => $retroMap->id,
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
        $format = Format::find(FormatConstants::MAPLIST);
        $format->map_submission_status = 'open';
        $format->save();

        // Mock NK API to return map exists
        NinjaKiwiApiClient::fakeMapExists([$map->code => true]);

        // Create map with placement beyond map_count (50)
        MapListMeta::factory()
            ->for($map)
            ->create([
                'placement_curver' => 60,
            ]);

        Storage::fake('public');

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps/submissions', [
                'code' => $map->code,
                'format_id' => $format->id,
                'proposed' => 0,
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
        $format = Format::find(FormatConstants::EXPERT_LIST);
        $format->map_submission_status = 'open';
        $format->save();

        // Mock NK API to return map exists
        NinjaKiwiApiClient::fakeMapExists([$map->code => true]);

        // Map has no meta (not in any list)

        Storage::fake('public');

        $submissionId = $this->actingAs($user, 'discord')
            ->postJson('/api/maps/submissions', [
                'code' => $map->code,
                'format_id' => $format->id,
                'proposed' => 0,
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
        $format->map_submission_status = 'open';
        $format->save();

        // Mock NK API to return map exists
        NinjaKiwiApiClient::fakeMapExists([$map->code => true]);

        Storage::fake('public');

        // Missing file
        $this->actingAs($user, 'discord')
            ->postJson('/api/maps/submissions', [
                'code' => $map->code,
                'format_id' => $format->id,
                'proposed' => 0,
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
        $format->map_submission_status = 'open';
        $format->save();

        // Mock NK API to return map exists
        NinjaKiwiApiClient::fakeMapExists([$map->code => true]);

        Storage::fake('public');
        $file = UploadedFile::fake()->image('proof.jpg');

        $submissionId = $this->actingAs($user, 'discord')
            ->postJson('/api/maps/submissions', [
                'code' => $map->code,
                'format_id' => $format->id,
                'proposed' => 0,
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
