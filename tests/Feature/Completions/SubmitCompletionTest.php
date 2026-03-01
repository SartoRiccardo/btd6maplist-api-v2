<?php

namespace Tests\Feature\Completions;

use App\Constants\FormatConstants;
use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\Config;
use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Traits\TestsDiscordAuthMiddleware;
use Tests\TestCase;
use PHPUnit\Metadata\Group;

class SubmitCompletionTest extends TestCase
{
    use TestsDiscordAuthMiddleware;

    protected function endpoint(): string
    {
        return '/api/completions/submit';
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

    // Helper method to create a map valid for a specific format
    protected function createMapForFormat(int $formatId): Map
    {
        $map = Map::factory()->create();

        $metaData = ['code' => $map->code];

        if ($formatId === FormatConstants::MAPLIST) {
            $metaData['placement_curver'] = 1;
        } elseif ($formatId === FormatConstants::MAPLIST_ALL_VERSIONS) {
            $metaData['placement_allver'] = 1;
        } elseif ($formatId === FormatConstants::EXPERT_LIST) {
            $metaData['difficulty'] = 1;
        } elseif ($formatId === FormatConstants::BEST_OF_THE_BEST) {
            $metaData['botb_difficulty'] = 1;
        } elseif ($formatId === FormatConstants::NOSTALGIA_PACK) {
            // Don't set remake_of since it requires a foreign key to retro_maps
            $metaData['botb_difficulty'] = 1;
        }

        MapListMeta::factory()->for($map)->create($metaData);

        return $map;
    }

    // Helper to create proof images
    protected function createProofImages(int $count = 1): array
    {
        $images = [];
        for ($i = 0; $i < $count; $i++) {
            $images[] = UploadedFile::fake()
                ->image("proof{$i}.jpg", 1024, 1024)
                ->size(100);
        }
        return $images;
    }

    // ========== PERMISSION TESTS ==========

    #[Group('post')]
    #[Group('completions')]
    public function test_user_without_create_completion_submission_permission_returns_403(): void
    {
        $map = $this->createMapForFormat(FormatConstants::MAPLIST);
        $user = $this->createUserWithPermissions([]); // No permissions

        $player = User::factory()->create(['discord_id' => '123456789012345678']);

        $payload = [
            'map' => $map->code,
            'format_id' => FormatConstants::MAPLIST,
            'players' => [$player->discord_id],
            'proof_images' => $this->createProofImages(),
        ];

        $this->actingAs($user, 'discord')
            ->postJson($this->endpoint(), $payload)
            ->assertStatus(422)
            ->assertJson(['message' => 'You do not have permission to submit completions for this format.']);
    }

    // ========== FORMAT STATUS TESTS ==========

    #[Group('post')]
    #[Group('completions')]
    public function test_format_closed_returns_422(): void
    {
        $map = $this->createMapForFormat(FormatConstants::MAPLIST_ALL_VERSIONS);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST_ALL_VERSIONS => ['create:completion_submission']]);

        // Format is already closed from seeder

        $player = User::factory()->create(['discord_id' => '123456789012345678']);

        $payload = [
            'map' => $map->code,
            'format_id' => FormatConstants::MAPLIST_ALL_VERSIONS,
            'players' => [$player->discord_id],
            'proof_images' => $this->createProofImages(),
        ];

        $this->actingAs($user, 'discord')
            ->postJson($this->endpoint(), $payload)
            ->assertStatus(422)
            ->assertJson(['message' => 'Submissions are closed for this format.']);
    }

    #[Group('post')]
    #[Group('completions')]
    public function test_lcc_only_format_without_lcc_returns_422(): void
    {
        $format = FormatConstants::BEST_OF_THE_BEST;
        $map = $this->createMapForFormat($format);
        $user = $this->createUserWithPermissions([$format => ['create:completion_submission']]);

        $player = User::factory()->create(['discord_id' => '123456789012345678']);

        $payload = [
            'map' => $map->code,
            'format_id' => $format,
            'players' => [$player->discord_id],
            'proof_images' => $this->createProofImages(),
        ];

        $this->actingAs($user, 'discord')
            ->postJson($this->endpoint(), $payload)
            ->assertStatus(422)
            ->assertJson(['message' => 'This format requires Least Cost Chimps data to be provided.']);
    }

    // ========== MAPLIST VALIDATION TESTS ==========

    #[Group('post')]
    #[Group('completions')]
    public function test_maplist_placement_exceeds_map_count_returns_422(): void
    {
        Config::factory()->name('map_count')->create(['value' => 50]);

        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create(['placement_curver' => 51]); // Exceeds map_count

        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['create:completion_submission']]);

        $player = User::factory()->create(['discord_id' => '123456789012345678']);

        $payload = [
            'map' => $map->code,
            'format_id' => FormatConstants::MAPLIST,
            'players' => [$player->discord_id],
            'proof_images' => $this->createProofImages(),
        ];

        $this->actingAs($user, 'discord')
            ->postJson($this->endpoint(), $payload)
            ->assertStatus(422)
            ->assertJson(['message' => 'Map placement is not within the valid range (1-50) for this format.']);
    }

    #[Group('post')]
    #[Group('completions')]
    public function test_maplist_black_border_requires_video(): void
    {
        $map = $this->createMapForFormat(FormatConstants::MAPLIST);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['create:completion_submission']]);

        $player = User::factory()->create(['discord_id' => '123456789012345678']);

        $payload = [
            'map' => $map->code,
            'format_id' => FormatConstants::MAPLIST,
            'players' => [$player->discord_id],
            'black_border' => true,
            'proof_images' => $this->createProofImages(),
        ];

        $this->actingAs($user, 'discord')
            ->postJson($this->endpoint(), $payload)
            ->assertStatus(422)
            ->assertJson(['message' => 'Video proof is required for this submission.']);
    }

    // ========== EXPERT LIST VIDEO LOGIC TESTS ==========

    #[Group('post')]
    #[Group('completions')]
    public function test_expert_list_no_geraldo_on_difficulty_2_without_video_succeeds(): void
    {
        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create(['difficulty' => 2]); // Medium Expert

        $user = $this->createUserWithPermissions([FormatConstants::EXPERT_LIST => ['create:completion_submission']]);

        $player = User::factory()->create(['discord_id' => '123456789012345678']);

        $payload = [
            'map' => $map->code,
            'format_id' => FormatConstants::EXPERT_LIST,
            'players' => [$player->discord_id],
            'no_geraldo' => true,
            'proof_images' => $this->createProofImages(),
        ];

        $this->actingAs($user, 'discord')
            ->postJson($this->endpoint(), $payload)
            ->assertStatus(201);
    }

    #[Group('post')]
    #[Group('completions')]
    public function test_expert_list_no_geraldo_on_difficulty_3_without_video_returns_422(): void
    {
        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create(['difficulty' => 3]); // True Expert - requires video

        $user = $this->createUserWithPermissions([FormatConstants::EXPERT_LIST => ['create:completion_submission']]);

        $player = User::factory()->create(['discord_id' => '123456789012345678']);

        $payload = [
            'map' => $map->code,
            'format_id' => FormatConstants::EXPERT_LIST,
            'players' => [$player->discord_id],
            'no_geraldo' => true,
            'proof_images' => $this->createProofImages(),
        ];

        $this->actingAs($user, 'discord')
            ->postJson($this->endpoint(), $payload)
            ->assertStatus(422)
            ->assertJson(['message' => 'Video proof is required for this submission.']);
    }

    // ========== RECORDING REQUIREMENT TESTS ==========

    #[Group('post')]
    #[Group('completions')]
    public function test_user_with_recording_requirement_must_provide_videos(): void
    {
        $map = $this->createMapForFormat(FormatConstants::NOSTALGIA_PACK);
        $user = $this->createUserWithPermissions([
            FormatConstants::NOSTALGIA_PACK => [
                'create:completion_submission',
                'require:completion_submission:recording'
            ]
        ]);

        $player = User::factory()->create(['discord_id' => '123456789012345678']);

        $payload = [
            'map' => $map->code,
            'format_id' => FormatConstants::NOSTALGIA_PACK,
            'players' => [$player->discord_id],
            'lcc' => ['leftover' => 1000],
            'proof_images' => $this->createProofImages(),
        ];

        $this->actingAs($user, 'discord')
            ->postJson($this->endpoint(), $payload)
            ->assertStatus(422)
            ->assertJson(['message' => 'Video proof is required for your submission.']);
    }

    // ========== HAPPY PATH TESTS ==========

    #[Group('post')]
    #[Group('completions')]
    public function test_successful_submission_creates_pending_completion(): void
    {
        Storage::fake('public');

        $map = $this->createMapForFormat(FormatConstants::MAPLIST);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['create:completion_submission']]);

        $player = User::factory()->create(['discord_id' => '123456789012345678']);

        $payload = [
            'map' => $map->code,
            'format_id' => FormatConstants::MAPLIST,
            'players' => [$player->discord_id],
            'proof_images' => $this->createProofImages(),
        ];

        $response = $this->actingAs($user, 'discord')
            ->postJson($this->endpoint(), $payload)
            ->assertStatus(201)
            ->assertJsonStructure(['id']);

        $completionId = $response->json('id');

        // Verify the completion can be retrieved via GET endpoint
        $actual = $this->actingAs($user, 'discord')
            ->getJson("/api/completions/{$completionId}")
            ->assertStatus(200)
            ->json();

        $this->assertEquals($completionId, $actual['id']);
        $this->assertEquals($map->code, $actual['map_code']);
        $this->assertNull($actual['accepted_by'], 'Completion should be pending');
    }
}
