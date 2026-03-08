<?php

namespace Tests\Feature\Completions;

use App\Constants\FormatConstants;
use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\Traits\TestsDiscordAuthMiddleware;
use Tests\TestCase;
use PHPUnit\Metadata\Group;

class StoreCompletionTest extends TestCase
{
    use TestsDiscordAuthMiddleware;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
    }

    protected function endpoint(): string
    {
        return '/api/completions';
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

    /**
     * Admin without edit:completion permission returns 403
     */
    #[Group('post')]
    #[Group('completions')]
    public function test_admin_without_permission_returns_403(): void
    {
        $map = $this->createMapForFormat(FormatConstants::MAPLIST);
        $user = $this->createUserWithPermissions([]);

        $player = User::factory()->create(['discord_id' => '123456789012345678']);

        $payload = [
            'map' => $map->code,
            'format_id' => FormatConstants::MAPLIST,
            'players' => [$player->discord_id],
            'proof_images' => $this->createProofImages(),
        ];

        $this->actingAs($user, 'discord')
            ->postJson($this->endpoint(), $payload)
            ->assertStatus(403)
            ->assertJson(['message' => 'Forbidden - You do not have permission to create completions for this format.']);
    }

    // ========== BUSINESS RULE TESTS ==========

    /**
     * Admin in players returns 422
     */
    #[Group('post')]
    #[Group('completions')]
    public function test_admin_in_players_returns_422(): void
    {
        $map = $this->createMapForFormat(FormatConstants::MAPLIST);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);

        $payload = [
            'map' => $map->code,
            'format_id' => FormatConstants::MAPLIST,
            'players' => [$user->discord_id],
            'proof_images' => $this->createProofImages(),
        ];

        $this->actingAs($user, 'discord')
            ->postJson($this->endpoint(), $payload)
            ->assertStatus(422)
            ->assertJson(['message' => 'You cannot submit a completion that includes yourself.']);
    }

    /**
     * Empty players array returns 422
     */
    #[Group('post')]
    #[Group('completions')]
    public function test_empty_players_array_returns_422(): void
    {
        $map = $this->createMapForFormat(FormatConstants::MAPLIST);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);

        $payload = [
            'map' => $map->code,
            'format_id' => FormatConstants::MAPLIST,
            'players' => [],
            'proof_images' => $this->createProofImages(),
        ];

        $this->actingAs($user, 'discord')
            ->postJson($this->endpoint(), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['players']);
    }

    /**
     * Duplicate players returns 422 with exact key path
     */
    #[Group('post')]
    #[Group('completions')]
    public function test_duplicate_players_returns_422(): void
    {
        $map = $this->createMapForFormat(FormatConstants::MAPLIST);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);
        $player = User::factory()->create(['discord_id' => '123456789012345678']);

        $payload = [
            'map' => $map->code,
            'format_id' => FormatConstants::MAPLIST,
            'players' => [$player->discord_id, $player->discord_id],
            'proof_images' => $this->createProofImages(),
        ];

        $this->actingAs($user, 'discord')
            ->postJson($this->endpoint(), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['players.1']);
    }

    /**
     * Invalid player ID format returns 422
     */
    #[Group('post')]
    #[Group('completions')]
    public function test_invalid_player_id_format_returns_422(): void
    {
        $map = $this->createMapForFormat(FormatConstants::MAPLIST);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);

        $payload = [
            'map' => $map->code,
            'format_id' => FormatConstants::MAPLIST,
            'players' => ['invalid_id'],
            'proof_images' => $this->createProofImages(),
        ];

        $this->actingAs($user, 'discord')
            ->postJson($this->endpoint(), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['players.0']);
    }

    /**
     * Non-existent player ID returns 422
     */
    #[Group('post')]
    #[Group('completions')]
    public function test_nonexistent_player_id_returns_422(): void
    {
        $map = $this->createMapForFormat(FormatConstants::MAPLIST);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);

        $payload = [
            'map' => $map->code,
            'format_id' => FormatConstants::MAPLIST,
            'players' => ['123456789012345678'], // Doesn't exist
            'proof_images' => $this->createProofImages(),
        ];

        $this->actingAs($user, 'discord')
            ->postJson($this->endpoint(), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['players.0']);
    }

    // ========== MAP VALIDATION TESTS ==========

    /**
     * Invalid map code returns 422 (validation error from exists:maps,code)
     */
    #[Group('post')]
    #[Group('completions')]
    public function test_invalid_map_code_returns_422(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);

        $player = User::factory()->create(['discord_id' => '123456789012345678']);

        $payload = [
            'map' => 'NOEXIST', // 7 characters, within 10 char limit
            'format_id' => FormatConstants::MAPLIST,
            'players' => [$player->discord_id],
            'proof_images' => $this->createProofImages(),
        ];

        $this->actingAs($user, 'discord')
            ->postJson($this->endpoint(), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['map']);
    }

    /**
     * Admin can submit to any map (map validation skipped)
     */
    #[Group('post')]
    #[Group('completions')]
    public function test_admin_can_submit_to_any_map(): void
    {
        // Create map that's NOT valid for Maplist (no placement_curver)
        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create(['placement_curver' => null]);

        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);
        $player = User::factory()->create(['discord_id' => '123456789012345678']);

        $payload = [
            'map' => $map->code,
            'format_id' => FormatConstants::MAPLIST,
            'players' => [$player->discord_id],
            'proof_images' => $this->createProofImages(),
        ];

        $this->actingAs($user, 'discord')
            ->postJson($this->endpoint(), $payload)
            ->assertStatus(201)
            ->assertJson(['id' => true]);
    }

    // ========== VALIDATION TESTS ==========

    /**
     * Empty payload returns 422 with all required field errors
     */
    #[Group('post')]
    #[Group('completions')]
    public function test_empty_payload_returns_all_required_field_errors(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);

        $actual = $this->actingAs($user, 'discord')
            ->postJson($this->endpoint(), [])
            ->assertStatus(422)
            ->json();

        $expected = ['map', 'format_id', 'players', 'proof_images'];
        $actualKeys = array_keys($actual['errors']);
        sort($expected);
        sort($actualKeys);
        $this->assertEquals($expected, $actualKeys);
    }

    /**
     * Too many proof_images (>4) returns 422
     */
    #[Group('post')]
    #[Group('completions')]
    public function test_too_many_proof_images_returns_422(): void
    {
        $map = $this->createMapForFormat(FormatConstants::MAPLIST);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);
        $player = User::factory()->create(['discord_id' => '123456789012345678']);

        $payload = [
            'map' => $map->code,
            'format_id' => FormatConstants::MAPLIST,
            'players' => [$player->discord_id],
            'proof_images' => $this->createProofImages(5), // Too many
        ];

        $this->actingAs($user, 'discord')
            ->postJson($this->endpoint(), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['proof_images']);
    }

    /**
     * Too many proof_videos (>10) returns 422
     */
    #[Group('post')]
    #[Group('completions')]
    public function test_too_many_proof_videos_returns_422(): void
    {
        $map = $this->createMapForFormat(FormatConstants::MAPLIST);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);
        $player = User::factory()->create(['discord_id' => '123456789012345678']);

        $payload = [
            'map' => $map->code,
            'format_id' => FormatConstants::MAPLIST,
            'players' => [$player->discord_id],
            'proof_images' => $this->createProofImages(),
            'proof_videos' => array_fill(0, 11, 'https://youtube.com/video'), // 11 videos
        ];

        $this->actingAs($user, 'discord')
            ->postJson($this->endpoint(), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['proof_videos']);
    }

    /**
     * Invalid video URL returns 422
     */
    #[Group('post')]
    #[Group('completions')]
    public function test_invalid_video_url_returns_422(): void
    {
        $map = $this->createMapForFormat(FormatConstants::MAPLIST);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);
        $player = User::factory()->create(['discord_id' => '123456789012345678']);

        $payload = [
            'map' => $map->code,
            'format_id' => FormatConstants::MAPLIST,
            'players' => [$player->discord_id],
            'proof_images' => $this->createProofImages(),
            'proof_videos' => ['not-a-url'],
        ];

        $this->actingAs($user, 'discord')
            ->postJson($this->endpoint(), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['proof_videos.0']);
    }

    /**
     * Invalid image file type returns 422
     */
    #[Group('post')]
    #[Group('completions')]
    public function test_invalid_image_file_type_returns_422(): void
    {
        $map = $this->createMapForFormat(FormatConstants::MAPLIST);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);
        $player = User::factory()->create(['discord_id' => '123456789012345678']);

        $invalidFile = UploadedFile::fake()->create('invalid.pdf', 100);

        $payload = [
            'map' => $map->code,
            'format_id' => FormatConstants::MAPLIST,
            'players' => [$player->discord_id],
            'proof_images' => [$invalidFile],
        ];

        $this->actingAs($user, 'discord')
            ->post($this->endpoint(), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['proof_images.0']);
    }

    /**
     * null subm_notes works
     */
    #[Group('post')]
    #[Group('completions')]
    public function test_null_submission_notes_works(): void
    {
        $map = $this->createMapForFormat(FormatConstants::MAPLIST);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);
        $player = User::factory()->create(['discord_id' => '123456789012345678']);

        $payload = [
            'map' => $map->code,
            'format_id' => FormatConstants::MAPLIST,
            'players' => [$player->discord_id],
            'proof_images' => $this->createProofImages(),
            'subm_notes' => null,
        ];

        $this->actingAs($user, 'discord')
            ->postJson($this->endpoint(), $payload)
            ->assertStatus(201)
            ->assertJson(['id' => true]);
    }

    // ========== HAPPY PATH TESTS ==========

    /**
     * Admin creates completion (happy path)
     */
    #[Group('post')]
    #[Group('completions')]
    public function test_admin_creates_completion_happy_path(): void
    {
        $map = $this->createMapForFormat(FormatConstants::MAPLIST);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);
        $player = User::factory()->create(['discord_id' => '123456789012345678']);

        $payload = [
            'map' => $map->code,
            'format_id' => FormatConstants::MAPLIST,
            'black_border' => true,
            'no_geraldo' => false,
            'players' => [$player->discord_id],
            'proof_images' => $this->createProofImages(),
            'subm_notes' => 'Test submission notes',
        ];

        $response = $this->actingAs($user, 'discord')
            ->postJson($this->endpoint(), $payload)
            ->assertStatus(201)
            ->assertJson(['id' => true]);

        $completionId = $response->json('id');

        // Verify with GET
        $actual = $this->actingAs($user, 'discord')
            ->getJson("/api/completions/{$completionId}")
            ->assertStatus(200)
            ->json();

        $actual = $this->pick($actual, [
            'map_code',
            'format_id',
            'black_border',
            'no_geraldo',
            'subm_notes',
            'accepted_by.discord_id',
            'players.*.discord_id',
        ]);

        $expected = [
            'map_code' => $map->code,
            'format_id' => FormatConstants::MAPLIST,
            'black_border' => true,
            'no_geraldo' => false,
            'subm_notes' => 'Test submission notes',
            'accepted_by' => [
                'discord_id' => $user->discord_id,
            ],
            'players' => [
                ['discord_id' => $player->discord_id],
            ],
        ];

        $this->assertEquals($expected, $actual);
    }

    /**
     * Full happy path with images, videos, LCC
     */
    #[Group('post')]
    #[Group('completions')]
    public function test_full_happy_path_with_images_videos_lcc(): void
    {
        Storage::fake('public');

        $map = $this->createMapForFormat(FormatConstants::MAPLIST);
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);
        $player = User::factory()->create(['discord_id' => '123456789012345678']);

        $payload = [
            'map' => $map->code,
            'format_id' => FormatConstants::MAPLIST,
            'black_border' => true,
            'no_geraldo' => false,
            'players' => [$player->discord_id],
            'proof_images' => $this->createProofImages(2),
            'proof_videos' => ['https://youtube.com/watch?v=test1', 'https://youtube.com/watch?v=test2'],
            'lcc' => ['leftover' => 5000],
            'subm_notes' => 'Full test notes',
        ];

        $response = $this->actingAs($user, 'discord')
            ->post($this->endpoint(), $payload)
            ->assertStatus(201)
            ->assertJson(['id' => true]);

        $completionId = $response->json('id');

        // Verify with GET
        $actual = $this->actingAs($user, 'discord')
            ->getJson("/api/completions/{$completionId}")
            ->assertStatus(200)
            ->json();

        // Verify proofs (dynamic URLs, just check count)
        $this->assertCount(2, $this->pick($actual, ['subm_proof_img'])['subm_proof_img']);

        $actual = $this->pick($actual, [
            'map_code',
            'format_id',
            'black_border',
            'no_geraldo',
            'subm_notes',
            'accepted_by.discord_id',
            'players.*.discord_id',
            'lcc.leftover',
            'subm_proof_vid',
        ]);

        $expected = [
            'map_code' => $map->code,
            'format_id' => FormatConstants::MAPLIST,
            'black_border' => true,
            'no_geraldo' => false,
            'subm_notes' => 'Full test notes',
            'accepted_by' => [
                'discord_id' => $user->discord_id,
            ],
            'players' => [
                ['discord_id' => $player->discord_id],
            ],
            'lcc' => [
                'leftover' => 5000,
            ],
            'subm_proof_vid' => [
                'https://youtube.com/watch?v=test1',
                'https://youtube.com/watch?v=test2',
            ],
        ];

        $this->assertEquals($expected, $actual);

        // Verify files were stored (check directory has 2 files)
        $files = Storage::disk('public')->files("completion_proofs/{$completionId}");
        $this->assertCount(2, $files);
        $this->assertStringContainsString('img_', $files[0]);
        $this->assertStringEndsWith('.jpg', $files[0]);
    }
}
