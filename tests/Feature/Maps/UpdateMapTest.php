<?php

namespace Tests\Feature;

use App\Constants\FormatConstants;
use App\Models\Config;
use App\Models\Map;
use App\Models\MapAlias;
use App\Models\MapListMeta;
use App\Models\RetroMap;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Traits\TestsDiscordAuthMiddleware;
use Tests\TestCase;

class UpdateMapTest extends TestCase
{
    use TestsDiscordAuthMiddleware;

    protected Map $testMap;
    protected MapListMeta $testMeta;

    protected function endpoint(): string
    {
        return '/api/maps/TESTCODE';
    }

    protected function method(): string
    {
        return 'PUT';
    }

    protected function requestData(): array
    {
        return [
            'name' => 'Updated Test Map',
        ];
    }

    protected function expectedSuccessStatusCode(): int
    {
        return 200;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Create a map to update for each test
        $this->testMap = Map::factory()->create(['code' => 'TESTCODE', 'name' => 'Original Test Map']);
        $this->testMeta = MapListMeta::factory()->for($this->testMap)->create();
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_store_map_with_no_edit_map_permission_returns_403(): void
    {
        $user = $this->createUserWithPermissions([]);

        $payload = [
            'name' => 'Updated Test Map',
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(403)
            ->assertJson(['message' => 'Forbidden - You do not have permission to edit maps']);
    }

    /**
     * @dataProvider updateMapFiltersByPermissionProvider
     */
    #[Group('update')]
    #[Group('maps')]
    public function test_store_map_filters_by_permission(int|null $formatId, string $field): void
    {
        $retroMap = RetroMap::factory()->create();
        $user = $this->createUserWithPermissions([$formatId => ['edit:map']]);

        // Set all meta fields to fixed values to avoid flaky tests
        $this->testMeta->update([
            'placement_curver' => 10,
            'placement_allver' => 20,
            'difficulty' => 2,
            'optimal_heros' => ['OldHero'],
            'botb_difficulty' => 1,
            'remake_of' => null,
        ]);

        $payload = [
            'name' => 'Updated Test Map',
            'placement_curver' => 1,
            'placement_allver' => 1,
            'difficulty' => 3,
            'optimal_heros' => ['Quincy'],
            'botb_difficulty' => 2,
            'remake_of' => $retroMap->id,
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(204);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/TESTCODE')
            ->assertStatus(200)
            ->json();

        // The field they have permission for should be updated to the new value
        $this->assertEquals($payload[$field], $actual[$field]);

        // All other permission-controlled meta fields should retain their original fixed values
        // optimal_heros is NOT permission-filtered, so we don't check it
        $metaFields = [
            'placement_curver' => 10,
            'placement_allver' => 20,
            'difficulty' => 2,
            'botb_difficulty' => 1,
            'remake_of' => null,
        ];

        foreach ($metaFields as $metaField => $expectedValue) {
            if ($metaField !== $field) {
                $this->assertEquals($expectedValue, $actual[$metaField], "Field {$metaField} should retain original value for format " . ($formatId ?? 'null'));
            }
        }
    }

    public static function updateMapFiltersByPermissionProvider(): array
    {
        return [
            'maplist permission sets placement_curver' => [FormatConstants::MAPLIST, 'placement_curver'],
            'maplist all versions permission sets placement_allver' => [FormatConstants::MAPLIST_ALL_VERSIONS, 'placement_allver'],
            'expert list permission sets difficulty' => [FormatConstants::EXPERT_LIST, 'difficulty'],
            'best of the best permission sets botb_difficulty' => [FormatConstants::BEST_OF_THE_BEST, 'botb_difficulty'],
            'nostalgia pack sets remake_of' => [FormatConstants::NOSTALGIA_PACK, 'remake_of'],
        ];
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_store_map_with_global_permission_sets_all_meta_fields(): void
    {
        $retroMap = RetroMap::factory()->create();
        $user = $this->createUserWithPermissions([null => ['edit:map']]);

        $payload = [
            'name' => 'Updated Test Map',
            'r6_start' => 'https://example.com/r6-start.jpg',
            'map_data' => '{}',
            'map_preview_url' => 'https://example.com/preview.png',
            'map_notes' => 'Test notes',
            'placement_curver' => 1,
            'placement_allver' => 1,
            'difficulty' => 3,
            'optimal_heros' => ['Quincy'],
            'botb_difficulty' => 2,
            'remake_of' => $retroMap->id,
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(204);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/TESTCODE')
            ->assertStatus(200)
            ->json();

        $expected = [
            'placement_curver' => 1,
            'placement_allver' => 1,
            'difficulty' => 3,
            'optimal_heros' => ['Quincy'],
            'botb_difficulty' => 2,
            'remake_of' => $retroMap->id,
        ];
        $this->assertEquals($expected, array_intersect_key($actual, $expected));
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_store_map_with_empty_payload_returns_all_required_field_errors(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $actual = $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', [])
            ->assertStatus(422)
            ->json();

        $expected = ['name'];
        $actualKeys = array_keys($actual['errors']);
        sort($expected);
        sort($actualKeys);
        $this->assertEquals($expected, $actualKeys);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_update_nonexistent_map_returns_404(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $payload = [
            'name' => 'Updated Test Map',
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/maps/NONEXISTENT', $payload)
            ->assertStatus(404)
            ->assertJson(['message' => 'Not Found']);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_update_map_with_existing_aliases_does_not_error(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        // Add some initial aliases to TESTCODE
        MapAlias::factory()
            ->for($this->testMap, 'map')
            ->count(2)
            ->sequence(fn($sequence) => ['alias' => 'ExistingAlias' . ($sequence->index + 1)])
            ->create();

        $payload = [
            'name' => 'Updated Test Map',
            'aliases' => ['ExistingAlias1', 'ExistingAlias2', 'NewAlias'],
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(204);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/TESTCODE')
            ->assertStatus(200)
            ->json();

        $this->assertCount(3, $actual['aliases']);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_update_map_with_placement_curver_exceeding_max_when_already_set_returns_error_with_correct_max(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        // Set TESTCODE to have placement_curver = 5
        $this->testMeta->placement_curver = 5;
        $this->testMeta->save();

        // Create 5 additional maps with placement_curver set (total = 6 maps with curver)
        $maps = Map::factory()->count(5)->create();
        MapListMeta::factory()
            ->count(5)
            ->sequence(fn($sequence) => [
                'code' => $maps[$sequence->index]->code,
                'placement_curver' => $sequence->index + 6, // 6, 7, 8, 9, 10
            ])
            ->create();

        $payload = [
            'name' => 'Updated Test Map',
            'placement_curver' => 100,
        ];

        $errors = $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(422)
            ->json('errors');

        $this->assertNotEmpty($errors['placement_curver']);
        // When map already has placement_curver set, max = current total count (not +1 like for null -> value)
        // We have 10 maps with placement_curver, so max is 10
        $this->assertStringContainsString('10', $errors['placement_curver'][0]);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_update_map_with_placement_allver_exceeding_max_when_already_set_returns_error_with_correct_max(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST_ALL_VERSIONS => ['edit:map']]);

        // Set TESTCODE to have placement_allver = 5
        $this->testMeta->placement_allver = 5;
        $this->testMeta->save();

        // Create 5 additional maps with placement_allver set (total = 6 maps with allver)
        $maps = Map::factory()->count(5)->create();
        MapListMeta::factory()
            ->count(5)
            ->sequence(fn($sequence) => [
                'code' => $maps[$sequence->index]->code,
                'placement_allver' => $sequence->index + 6, // 6, 7, 8, 9, 10
            ])
            ->create();

        $payload = [
            'name' => 'Updated Test Map',
            'placement_allver' => 100,
        ];

        $errors = $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(422)
            ->json('errors');

        $this->assertNotEmpty($errors['placement_allver']);
        // When map already has placement_allver set, max = current total count (not +1 like for null -> value)
        // We have 10 maps with placement_allver, so max is 10
        $this->assertStringContainsString('10', $errors['placement_allver'][0]);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_store_map_with_invalid_name_returns_error(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $payload = [
            'name' => str_repeat('a', 256),
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_store_map_with_placement_curver_exceeding_max_returns_error_with_correct_max_value(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        // Ensure TESTCODE starts with null placement_curver (same as POST behavior)
        $this->testMeta->placement_curver = null;
        $this->testMeta->save();

        // Create 5 additional maps with placement_curver set (besides the one created in setUp)
        $maps = Map::factory()->count(5)->create();
        MapListMeta::factory()
            ->count(5)
            ->sequence(fn($sequence) => [
                'code' => $maps[$sequence->index]->code,
                'placement_curver' => $sequence->index + 1,
            ])
            ->create();

        $payload = [
            'name' => 'Updated Test Map',
            'placement_curver' => 100,
        ];

        $errors = $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(422)
            ->json('errors');

        $this->assertNotEmpty($errors['placement_curver']);
        // 5 existing maps + 1 for TESTCODE = max position 6 (same as POST)
        $this->assertStringContainsString('6', $errors['placement_curver'][0]);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_store_map_with_placement_allver_exceeding_max_returns_error_with_correct_max_value(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST_ALL_VERSIONS => ['edit:map']]);

        // Ensure TESTCODE starts with null placement_allver (same as POST behavior)
        $this->testMeta->placement_allver = null;
        $this->testMeta->save();

        // Create 5 additional maps with placement_allver set (besides the one created in setUp)
        $maps = Map::factory()->count(5)->create();
        MapListMeta::factory()
            ->count(5)
            ->sequence(fn($sequence) => [
                'code' => $maps[$sequence->index]->code,
                'placement_allver' => $sequence->index + 1,
            ])
            ->create();

        $payload = [
            'name' => 'Updated Test Map',
            'placement_allver' => 100,
        ];

        $errors = $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(422)
            ->json('errors');

        $this->assertNotEmpty($errors['placement_allver']);
        // 5 existing maps + 1 for TESTCODE = max position 6 (same as POST)
        $this->assertStringContainsString('6', $errors['placement_allver'][0]);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_store_map_with_multiple_invalid_fields_returns_all_errors(): void
    {
        $user = $this->createUserWithPermissions([null => ['edit:map']]);

        $payload = [
            'name' => 'Updated Test Map',
            'r6_start' => 'not_an_integer',
            'map_preview_url' => 'not_a_url',
            'map_notes' => str_repeat('a', 1001),
            'placement_curver' => 0,
            'placement_allver' => -1,
            'difficulty' => 10,
            'botb_difficulty' => -1,
            'remake_of' => 99999,
            'optimal_heros' => 'not_an_array',
            'creators' => [
                ['user_id' => 'invalid_user_id'],
                ['user_id' => '123'], // Too short
            ],
            'verifiers' => [
                ['user_id' => 'another_invalid'],
                ['user_id' => '-12345678901234567'], // Negative
            ],
        ];

        $actual = $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(422)
            ->json();

        $expected = ['r6_start', 'map_preview_url', 'map_notes', 'placement_curver', 'placement_allver', 'difficulty', 'botb_difficulty', 'remake_of', 'optimal_heros', 'creators.0.role', 'creators.0.user_id', 'creators.1.role', 'creators.1.user_id', 'verifiers.0.version', 'verifiers.0.user_id', 'verifiers.1.version', 'verifiers.1.user_id'];
        $actualKeys = array_keys($actual['errors']);
        sort($expected);
        sort($actualKeys);
        $this->assertEquals($expected, $actualKeys);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_store_map_optimal_heros_too_many_items_returns_error(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $payload = [
            'name' => 'Updated Test Map',
            'optimal_heros' => ['Quincy', 'Gwendolin', 'Striker', 'Obyn', 'Captain Churchill', 'Benjamin', 'Etienne', 'Sauda', 'Adora', 'Brickell', 'Geraldo'],
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['optimal_heros']);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_store_map_optimal_heros_item_too_long_returns_error(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $payload = [
            'name' => 'Updated Test Map',
            'optimal_heros' => [str_repeat('a', 51)],
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['optimal_heros.0']);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_store_map_optimal_heros_with_duplicates_returns_error(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $payload = [
            'name' => 'Updated Test Map',
            'optimal_heros' => ['Quincy', 'Gwendolin', 'quincy'], // Case-insensitive duplicate
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['optimal_heros.2']);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_store_map_happy_path_with_admin_sets_everything(): void
    {
        // Clear existing meta for TESTCODE to start fresh
        $this->testMeta->placement_curver = null;
        $this->testMeta->placement_allver = null;
        $this->testMeta->save();

        // Create existing maps with placements so we can set position 5 and 10
        $maps = Map::factory()->count(9)->create();
        MapListMeta::factory()
            ->count($maps->count())
            ->sequence(fn($sequence) => [
                'code' => $maps[$sequence->index]->code,
                'placement_curver' => $sequence->index < 4 ? $sequence->index + 1 : null,
                'placement_allver' => $sequence->index + 1,
            ])
            ->create();

        $retroMap = RetroMap::factory()->create();
        $creator1 = User::factory()->create();
        $creator2 = User::factory()->create();
        $verifier1 = User::factory()->create();
        $verifier2 = User::factory()->create();

        $user = $this->createUserWithPermissions([null => ['edit:map']]);

        $payload = [
            'name' => 'Updated Test Map',
            'r6_start' => 'https://example.com/r6-start.jpg',
            'map_data' => '{}',
            'map_preview_url' => 'https://example.com/preview.png',
            'map_notes' => 'Test notes',
            'placement_curver' => 5,
            'placement_allver' => 10,
            'difficulty' => 3,
            'optimal_heros' => ['Quincy', 'Gwendolin'],
            'botb_difficulty' => 2,
            'remake_of' => $retroMap->id,
            'creators' => [
                ['user_id' => $creator1->discord_id, 'role' => 'Gameplay'],
                ['user_id' => $creator2->discord_id, 'role' => 'Design'],
            ],
            'verifiers' => [
                ['user_id' => $verifier1->discord_id, 'version' => null],
                ['user_id' => $verifier2->discord_id, 'version' => null],
            ],
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(204);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/TESTCODE')
            ->assertStatus(200)
            ->json();

        // Remove nested objects for cleaner comparison
        foreach ($actual['creators'] as &$creator) {
            unset($creator['user']);
        }
        foreach ($actual['verifications'] as &$verification) {
            unset($verification['user']);
        }
        unset($actual['retro_map']);

        $expected = Map::jsonStructure([
            'code' => 'TESTCODE',
            'name' => 'Updated Test Map',
            'r6_start' => 'https://example.com/r6-start.jpg',
            'map_data' => '{}',
            'map_preview_url' => 'https://example.com/preview.png',
            'map_notes' => 'Test notes',
            'placement_curver' => 5,
            'placement_allver' => 10,
            'difficulty' => 3,
            'optimal_heros' => ['Quincy', 'Gwendolin'],
            'botb_difficulty' => 2,
            'remake_of' => $retroMap->id,
            'deleted_on' => null,
            'is_verified' => true,
            'aliases' => [],
            'creators' => [
                ['user_id' => $creator1->discord_id, 'role' => 'Gameplay'],
                ['user_id' => $creator2->discord_id, 'role' => 'Design'],
            ],
            'verifications' => [
                ['user_id' => $verifier1->discord_id, 'version' => null],
                ['user_id' => $verifier2->discord_id, 'version' => null],
            ],
        ], exclude: ['retro_map']);

        $this->assertEquals($expected, $actual);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_store_map_at_position_n_shifts_other_maps_by_one(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        // Ensure TESTCODE starts with null placement_curver
        $this->testMeta->placement_curver = null;
        $this->testMeta->save();

        // Create 5 maps with placement_curver set
        $maps = Map::factory()->count(5)->create();
        MapListMeta::factory()
            ->count(5)
            ->sequence(fn($sequence) => [
                'code' => $maps[$sequence->index]->code,
                'placement_curver' => $sequence->index + 1,
            ])
            ->create();

        // Update TESTCODE (created in setUp) to position 3
        $payload = [
            'name' => 'Updated Test Map',
            'placement_curver' => 3,
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(204);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps')
            ->assertStatus(200)
            ->json('data');

        // Expected order: original 1-2 stay, TESTCODE at 3, original 3-5 shift to 4-6
        $expectedOrder = [
            $maps[0]->code,  // position 1
            $maps[1]->code,  // position 2
            'TESTCODE',       // position 3 (inserted here)
            $maps[2]->code,  // position 4 (was 3)
            $maps[3]->code,  // position 5 (was 4)
            $maps[4]->code,  // position 6 (was 5)
        ];

        $actualOrder = collect($actual)->sortBy('placement_curver')->pluck('code')->values()->toArray();
        $this->assertEquals($expectedOrder, $actualOrder);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_store_map_with_remake_of_steals_from_existing_remake(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::NOSTALGIA_PACK => ['edit:map']]);

        $retroMap = RetroMap::factory()->create();
        $existingMap = Map::factory()->create();
        MapListMeta::factory()->for($existingMap)->create(['remake_of' => $retroMap->id]);

        $payload = [
            'name' => 'Updated Test Map',
            'remake_of' => $retroMap->id,
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(204);

        // TESTCODE has the remake_of
        $newMap = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/TESTCODE')
            ->assertStatus(200)
            ->json();

        $this->assertEquals($retroMap->id, $newMap['remake_of']);

        // Old map no longer has the remake_of
        $oldMap = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/' . $existingMap->code)
            ->assertStatus(200)
            ->json();

        $this->assertNull($oldMap['remake_of']);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_store_map_with_empty_creators_and_verifiers_works(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $payload = [
            'name' => 'Updated Test Map',
            'creators' => [],
            'verifiers' => [],
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(204);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/TESTCODE')
            ->assertStatus(200)
            ->json();

        $this->assertIsArray($actual['creators']);
        $this->assertEmpty($actual['creators']);
        $this->assertIsArray($actual['verifications']);
        $this->assertEmpty($actual['verifications']);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_store_map_without_creators_and_verifiers_works(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $payload = [
            'name' => 'Just updating name',
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(204);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/TESTCODE')
            ->assertStatus(200)
            ->json();

        $this->assertIsArray($actual['creators']);
        $this->assertEmpty($actual['creators']);
        $this->assertIsArray($actual['verifications']);
        $this->assertEmpty($actual['verifications']);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_store_map_creators_without_role_returns_422(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);
        $creator = User::factory()->create();

        $payload = [
            'name' => 'Updated Test Map',
            'creators' => [
                ['user_id' => $creator->discord_id],
            ],
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['creators.0.role']);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_store_map_creators_with_role_works(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);
        $creator = User::factory()->create();

        $payload = [
            'name' => 'Updated Test Map',
            'creators' => [
                ['user_id' => $creator->discord_id, 'role' => 'Gameplay'],
            ],
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(204);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/TESTCODE')
            ->assertStatus(200)
            ->json();

        $this->assertCount(1, $actual['creators']);
        $this->assertEquals($creator->discord_id, $actual['creators'][0]['user_id']);
        $this->assertEquals('Gameplay', $actual['creators'][0]['role']);
    }

    #[Group('maps')]
    #[Group('update')]
    #[Group('maps')]
    public function test_store_map_verifiers_without_version_returns_422(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);
        $verifier = User::factory()->create();

        $payload = [
            'name' => 'Updated Test Map',
            'verifiers' => [
                ['user_id' => $verifier->discord_id],
            ],
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['verifiers.0.version']);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_update_placement_from_high_to_low_shifts_maps_between_up_by_one(): void
    {
        $user = $this->createUserWithPermissions([null => ['edit:map']]);

        // Set TESTCODE to position 5 initially
        $this->testMeta->placement_curver = 5;
        $this->testMeta->placement_allver = 5;
        $this->testMeta->save();

        // Create additional maps with explicit placements
        $maps = Map::factory()->count(5)->create();

        MapListMeta::factory()
            ->count(5)
            ->sequence(
                ['code' => $maps[0]->code, 'placement_curver' => 1, 'placement_allver' => 1],
                ['code' => $maps[1]->code, 'placement_curver' => 2, 'placement_allver' => 2],
                ['code' => $maps[2]->code, 'placement_curver' => 3, 'placement_allver' => 3],
                ['code' => $maps[3]->code, 'placement_curver' => 4, 'placement_allver' => 4],
                ['code' => $maps[4]->code, 'placement_curver' => 6, 'placement_allver' => 6], // 5 is TESTCODE
            )
            ->create();

        // Move TESTCODE from position 5 to position 2 (curver) and 5 to 4 (allver)
        $payload = [
            'name' => 'Updated Test Map',
            'placement_curver' => 2,
            'placement_allver' => 4,
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(204);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps')
            ->assertStatus(200)
            ->json('data');

        // Expected order for curver: TESTCODE moves from 5 to 2, shifting 2-4 up to 3-5
        $expectedOrderCurver = [
            $maps[0]->code,  // position 1 (unchanged)
            'TESTCODE',       // position 2 (moved from 5)
            $maps[1]->code,  // position 3 (was 2)
            $maps[2]->code,  // position 4 (was 3)
            $maps[3]->code,  // position 5 (was 4)
            $maps[4]->code,  // position 6 (unchanged)
        ];

        // Expected order for allver: TESTCODE moves from 5 to 4, shifting 4 up to 5
        $expectedOrderAllver = [
            $maps[0]->code,  // position 1 (unchanged)
            $maps[1]->code,  // position 2 (unchanged)
            $maps[2]->code,  // position 3 (unchanged)
            'TESTCODE',       // position 4 (moved from 5)
            $maps[3]->code,  // position 5 (was 4)
            $maps[4]->code,  // position 6 (unchanged)
        ];

        $actualOrderCurver = collect($actual)->sortBy('placement_curver')->pluck('code')->values()->toArray();
        $actualOrderAllver = collect($actual)->sortBy('placement_allver')->pluck('code')->values()->toArray();

        $this->assertEquals($expectedOrderCurver, $actualOrderCurver);
        $this->assertEquals($expectedOrderAllver, $actualOrderAllver);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_update_placement_from_low_to_high_shifts_maps_between_down_by_one(): void
    {
        $user = $this->createUserWithPermissions([null => ['edit:map']]);

        // Set TESTCODE to position 2 initially
        $this->testMeta->placement_curver = 2;
        $this->testMeta->placement_allver = 2;
        $this->testMeta->save();

        // Create additional maps with explicit placements - using sequence with array
        $maps = Map::factory()->count(5)->create();
        MapListMeta::factory()
            ->count(5)
            ->sequence(
                ['code' => $maps[0]->code, 'placement_curver' => 1, 'placement_allver' => 1],
                ['code' => $maps[1]->code, 'placement_curver' => 3, 'placement_allver' => 3], // 2 is TESTCODE
                ['code' => $maps[2]->code, 'placement_curver' => 4, 'placement_allver' => 4],
                ['code' => $maps[3]->code, 'placement_curver' => 5, 'placement_allver' => 5],
                ['code' => $maps[4]->code, 'placement_curver' => 6, 'placement_allver' => 6],
            )
            ->create();

        // Move TESTCODE from position 2 to position 5
        $payload = [
            'name' => 'Updated Test Map',
            'placement_curver' => 5,
            'placement_allver' => 5, // Move from 2 to 5
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(204);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps')
            ->assertStatus(200)
            ->json('data');

        // Expected order for both curver and allver: TESTCODE moves from 2 to 5, shifting 3-5 down to 2-4
        $expectedOrder = [
            $maps[0]->code,  // position 1 (unchanged)
            $maps[1]->code,  // position 2 (was 3)
            $maps[2]->code,  // position 3 (was 4)
            $maps[3]->code,  // position 4 (was 5)
            'TESTCODE',       // position 5 (moved from 2)
            $maps[4]->code,  // position 6 (unchanged)
        ];

        $actualOrderCurver = collect($actual)->sortBy('placement_curver')->pluck('code')->values()->toArray();
        $actualOrderAllver = collect($actual)->sortBy('placement_allver')->pluck('code')->values()->toArray();

        $this->assertEquals($expectedOrder, $actualOrderCurver);
        $this->assertEquals($expectedOrder, $actualOrderAllver);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_update_placement_from_value_to_null_shifts_lower_maps_up_by_one(): void
    {
        $user = $this->createUserWithPermissions([null => ['edit:map']]);

        // Set TESTCODE to position 3 initially
        $this->testMeta->placement_curver = 3;
        $this->testMeta->placement_allver = 4;
        $this->testMeta->save();

        // Create additional maps with explicit placements - using sequence with array
        $maps = Map::factory()->count(5)->create();

        MapListMeta::factory()
            ->count(5)
            ->sequence(
                ['code' => $maps[0]->code, 'placement_curver' => 1, 'placement_allver' => 1],
                ['code' => $maps[1]->code, 'placement_curver' => 2, 'placement_allver' => 2],
                ['code' => $maps[2]->code, 'placement_curver' => 4, 'placement_allver' => 3], // 3 is curver TESTCODE
                ['code' => $maps[3]->code, 'placement_curver' => 5, 'placement_allver' => 5], // 4 is allver TESTCODE
                ['code' => $maps[4]->code, 'placement_curver' => 6, 'placement_allver' => 6],
            )
            ->create();

        // Move TESTCODE from position 3/4 to null
        $payload = [
            'name' => 'Updated Test Map',
            'placement_curver' => null,
            'placement_allver' => null,
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(204);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps')
            ->assertStatus(200)
            ->json('data');

        // Expected order for curver (excluding nulls): 4-6 shifted up to 3-5
        $expectedOrderCurver = [
            $maps[0]->code,  // position 1 (unchanged)
            $maps[1]->code,  // position 2 (unchanged)
            $maps[2]->code,  // position 3 (was 4)
            $maps[3]->code,  // position 4 (was 5)
            $maps[4]->code,  // position 5 (was 6)
        ];

        // Expected order for allver (excluding nulls): 5-6 shifted up to 4-5
        $expectedOrderAllver = [
            $maps[0]->code,  // position 1 (unchanged)
            $maps[1]->code,  // position 2 (unchanged)
            $maps[2]->code,  // position 3 (unchanged)
            $maps[3]->code,  // position 4 (was 5)
            $maps[4]->code,  // position 5 (was 6)
        ];

        $actualOrderCurver = collect($actual)
            ->filter(fn($map) => $map['placement_curver'] !== null)
            ->sortBy('placement_curver')
            ->pluck('code')
            ->values()
            ->toArray();

        $actualOrderAllver = collect($actual)
            ->filter(fn($map) => $map['placement_allver'] !== null)
            ->sortBy('placement_allver')
            ->pluck('code')
            ->values()
            ->toArray();

        $this->assertEquals($expectedOrderCurver, $actualOrderCurver);
        $this->assertEquals($expectedOrderAllver, $actualOrderAllver);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_store_map_creators_with_various_roles_works(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);
        $creator1 = User::factory()->create();
        $creator2 = User::factory()->create();
        $creator3 = User::factory()->create();

        $payload = [
            'name' => 'Updated Test Map',
            'creators' => [
                ['user_id' => $creator1->discord_id, 'role' => 'Gameplay'],
                ['user_id' => $creator2->discord_id, 'role' => null],
                ['user_id' => $creator3->discord_id, 'role' => 'Design'],
            ],
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(204);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/TESTCODE')
            ->assertStatus(200)
            ->json();

        // Remove nested user objects for cleaner comparison
        foreach ($actual['creators'] as &$creator) {
            unset($creator['user']);
        }

        $expected = [
            ['user_id' => $creator1->discord_id, 'role' => 'Gameplay'],
            ['user_id' => $creator2->discord_id, 'role' => null],
            ['user_id' => $creator3->discord_id, 'role' => 'Design'],
        ];

        $this->assertEquals($expected, $actual['creators']);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_store_map_with_duplicate_creator_returns_error(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);
        $creator = User::factory()->create();

        $payload = [
            'name' => 'Updated Test Map',
            'creators' => [
                ['user_id' => $creator->discord_id],
                ['user_id' => $creator->discord_id],
            ],
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['creators.1.user_id']);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_store_map_verifiers_same_user_different_versions_works(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);
        $verifier = User::factory()->create();
        $currentVersion = Config::loadVars(['current_btd6_ver'])->get('current_btd6_ver');

        $payload = [
            'name' => 'Updated Test Map',
            'verifiers' => [
                ['user_id' => $verifier->discord_id, 'version' => null],
                ['user_id' => $verifier->discord_id, 'version' => $currentVersion],
            ],
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(204);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/TESTCODE')
            ->assertStatus(200)
            ->json();

        $this->assertCount(2, $actual['verifications']);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_store_map_with_duplicate_verifier_same_version_returns_error(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);
        $verifier = User::factory()->create();

        $payload = [
            'name' => 'Updated Test Map',
            'verifiers' => [
                ['user_id' => $verifier->discord_id, 'version' => null],
                ['user_id' => $verifier->discord_id, 'version' => null],
            ],
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['verifiers.1.user_id']);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_store_map_with_map_preview_file(): void
    {
        Storage::fake('public');

        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $file = UploadedFile::fake()->image('preview.jpg', 1024, 1024)->size(100);

        $payload = [
            'name' => 'Updated Test Map',
            'custom_map_preview_file' => $file,
        ];

        $this->actingAs($user, 'discord')
            ->put('/api/maps/TESTCODE', $payload)
            ->assertStatus(204);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/TESTCODE')
            ->assertStatus(200)
            ->json();

        $this->assertStringContainsString('/storage/map_previews/TESTCODE.jpg', $actual['map_preview_url']);
        Storage::disk('public')->assertExists('map_previews/TESTCODE.jpg');
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_store_map_with_both_preview_url_and_file_file_takes_precedence(): void
    {
        Storage::fake('public');

        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $file = UploadedFile::fake()->image('preview.png', 1024, 1024)->size(100);

        $payload = [
            'name' => 'Updated Test Map',
            'map_preview_url' => 'https://example.com/old-preview.png',
            'custom_map_preview_file' => $file,
        ];

        $this->actingAs($user, 'discord')
            ->put('/api/maps/TESTCODE', $payload)
            ->assertStatus(204);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/TESTCODE')
            ->assertStatus(200)
            ->json();

        // File should take precedence over URL
        $this->assertStringContainsString('/storage/map_previews/TESTCODE.png', $actual['map_preview_url']);
        $this->assertStringNotContainsString('https://example.com/old-preview.png', $actual['map_preview_url']);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_store_map_with_invalid_image_extension_returns_422(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $file = UploadedFile::fake()->create('invalid.pdf', 100); // Not an image

        $payload = [
            'custom_map_preview_file' => $file,
        ];

        $this->actingAs($user, 'discord')
            ->put('/api/maps/TESTCODE', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['custom_map_preview_file']);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_store_map_with_map_preview_file_too_large_returns_422(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $file = UploadedFile::fake()->image('huge.jpg', 1024, 1024)->size(4501);

        $payload = [
            'custom_map_preview_file' => $file,
        ];

        $this->actingAs($user, 'discord')
            ->put('/api/maps/TESTCODE', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['custom_map_preview_file']);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_store_map_with_r6_start_file(): void
    {
        Storage::fake('public');

        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $file = UploadedFile::fake()->image('r6_start.jpg', 1024, 1024)->size(100);

        $payload = [
            'name' => 'Updated Test Map',
            'r6_start_file' => $file,
        ];

        $this->actingAs($user, 'discord')
            ->put('/api/maps/TESTCODE', $payload)
            ->assertStatus(204);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/TESTCODE')
            ->assertStatus(200)
            ->json();

        $this->assertStringContainsString('/storage/r6_starts/TESTCODE.jpg', $actual['r6_start']);
        Storage::disk('public')->assertExists('r6_starts/TESTCODE.jpg');
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_store_map_with_both_r6_start_url_and_file_file_takes_precedence(): void
    {
        Storage::fake('public');

        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $file = UploadedFile::fake()->image('r6_start.png', 1024, 1024)->size(100);

        $payload = [
            'name' => 'Updated Test Map',
            'r6_start' => 'https://example.com/old-r6-start.png',
            'r6_start_file' => $file,
        ];

        $this->actingAs($user, 'discord')
            ->put('/api/maps/TESTCODE', $payload)
            ->assertStatus(204);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/TESTCODE')
            ->assertStatus(200)
            ->json();

        // File should take precedence over URL
        $this->assertStringContainsString('/storage/r6_starts/TESTCODE.png', $actual['r6_start']);
        $this->assertStringNotContainsString('https://example.com/old-r6-start.png', $actual['r6_start']);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_store_map_with_invalid_r6_start_file_extension_returns_422(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $file = UploadedFile::fake()->create('invalid.pdf', 100); // Not an image or video

        $payload = [
            'r6_start_file' => $file,
        ];

        $this->actingAs($user, 'discord')
            ->put('/api/maps/TESTCODE', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['r6_start_file']);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_store_map_with_r6_start_file_too_large_returns_422(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $file = UploadedFile::fake()->image('huge.jpg', 1024, 1024)->size(4501); // 4501KB = > 4.5MB

        $payload = [
            'r6_start_file' => $file,
        ];

        $this->actingAs($user, 'discord')
            ->put('/api/maps/TESTCODE', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['r6_start_file']);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_store_map_with_duplicate_alias_returns_422(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $payload = [
            'name' => 'Updated Test Map',
            'aliases' => ['TestAlias', 'testalias', 'AnotherAlias'],
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['aliases.1']);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_store_map_with_alias_taken_by_existing_map_returns_422(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $existingMap = Map::factory()->create();
        MapListMeta::factory()->for($existingMap)->create();
        MapAlias::factory()->for($existingMap, 'map')->create(['alias' => 'ExistingAlias']);

        $payload = [
            'name' => 'Updated Test Map',
            'aliases' => ['ExistingAlias'],
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['aliases.0']);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_store_map_with_alias_taken_by_deleted_map_works(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $existingMap = Map::factory()->create();
        MapListMeta::factory()->for($existingMap)->create(['deleted_on' => now()->subDay()]);
        MapAlias::factory()->for($existingMap, 'map')->create(['alias' => 'DeletedMapAlias']);

        $payload = [
            'name' => 'Updated Test Map',
            'aliases' => ['DeletedMapAlias'],
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(204);

        $this->assertDatabaseHas('map_aliases', [
            'alias' => 'deletedmapalias',
            'map_code' => 'TESTCODE',
        ]);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_store_map_with_alias_case_insensitive_returns_422(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $existingMap = Map::factory()->create();
        MapListMeta::factory()->for($existingMap)->create();
        MapAlias::factory()->for($existingMap, 'map')->create(['alias' => 'MyAlias']);

        $payload = [
            'name' => 'Updated Test Map',
            'aliases' => ['myalias'], // Different case
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['aliases.0']);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_store_map_with_nonexistent_creator_returns_422(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $payload = [
            'name' => 'Updated Test Map',
            'creators' => [
                ['user_id' => '123456789012345678'], // Non-existent user
            ],
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['creators.0.user_id']);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_store_map_with_nonexistent_verifier_returns_422(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $payload = [
            'name' => 'Updated Test Map',
            'verifiers' => [
                ['user_id' => '123456789012345678'], // Non-existent user
            ],
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['verifiers.0.user_id']);
    }

    /**
     * Dispatches a job when we have a submission for the map in a specific format,
     * and we update that format's key's value from NULL to a valid value.
     */
    #[Group('update')]
    #[Group('maps')]
    public function test_update_map_from_null_to_value_in_submission_dispatches_accept_submission_job(): void
    {
        $this->markTestIncomplete('Feature not yet implemented: dispatch accept submission job when updating a format field from null to a value when there is a pending submission');
    }

    /**
     * Map was submitted in another format, and we're setting a value for a different format.
     * Should NOT dispatch accept submission job.
     */
    #[Group('update')]
    #[Group('maps')]
    public function test_update_map_from_different_format_submission_does_not_dispatch_job(): void
    {
        $this->markTestIncomplete('Feature not yet implemented: no job dispatched when setting value for format different from submission format');
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_map_update_fails_if_all_meta_fields_are_cleared_to_null(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        // Set up map with placement_curver = 1
        $this->testMeta->update([
            'placement_curver' => 1,
            'placement_allver' => null,
            'difficulty' => null,
            'botb_difficulty' => null,
            'remake_of' => null,
        ]);

        $payload = [
            'name' => 'Updated Test Map',
            'placement_curver' => null, // Clear the only meta field
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(422)
            ->assertJson([
                'message' => 'At least one of the following fields must be provided: placement_curver, placement_allver, difficulty, botb_difficulty, remake_of',
                'errors' => [
                    'meta_fields' => ['At least one of the following fields must be provided: placement_curver, placement_allver, difficulty, botb_difficulty, remake_of'],
                ],
            ]);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_map_update_succeeds_if_at_least_one_meta_field_remains(): void
    {
        $user = $this->createUserWithPermissions([null => ['edit:map']]);

        // Set up map with multiple meta fields
        $retroMap = RetroMap::factory()->create();
        $this->testMeta->update([
            'placement_curver' => 1,
            'placement_allver' => 2,
            'difficulty' => 3,
            'botb_difficulty' => 1,
            'remake_of' => $retroMap->id,
        ]);

        // Clear some fields but leave at least one
        $payload = [
            'name' => 'Updated Test Map',
            'placement_curver' => null,
            'placement_allver' => null,
            'difficulty' => null,
            // Keep botb_difficulty and remake_of
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(204);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_map_update_with_permission_can_clear_all_but_retains_existing(): void
    {
        // User only has MAPLIST permission, tries to clear placement_curver
        // But other fields (that exist) should be preserved
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $retroMap = RetroMap::factory()->create();
        $this->testMeta->update([
            'placement_curver' => 1,
            'placement_allver' => 2,
            'difficulty' => 3,
            'botb_difficulty' => 1,
            'remake_of' => $retroMap->id,
        ]);

        $payload = [
            'name' => 'Updated Test Map',
            'placement_curver' => null, // Try to clear the only field user has permission for
        ];

        // This should succeed because other fields (not in user's permission scope) are preserved
        $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(204);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/TESTCODE')
            ->assertStatus(200)
            ->json();

        // placement_curver should be null (user cleared it)
        $this->assertNull($actual['placement_curver']);
        // Other fields should be preserved (user doesn't have permission to change them)
        $this->assertEquals(2, $actual['placement_allver']);
        $this->assertEquals(3, $actual['difficulty']);
        $this->assertEquals(1, $actual['botb_difficulty']);
        $this->assertEquals($retroMap->id, $actual['remake_of']);
    }

    #[Group('update')]
    #[Group('maps')]
    public function test_map_update_fails_if_provided_field_is_not_in_user_permissions(): void
    {
        // User has EXPERT_LIST permission (controls difficulty), but tries to set placement_curver (MAPLIST permission)
        // while all other fields are null
        $user = $this->createUserWithPermissions([FormatConstants::EXPERT_LIST => ['edit:map']]);

        // Set up map with only difficulty set (field user has permission for)
        $this->testMeta->update([
            'placement_curver' => null,
            'placement_allver' => null,
            'difficulty' => 3,
            'botb_difficulty' => null,
            'remake_of' => null,
        ]);

        $payload = [
            'name' => 'Updated Test Map',
            'difficulty' => null, // Clear the only field user has permission for
            'placement_curver' => 1, // Try to set a field user doesn't have permission for
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/maps/TESTCODE', $payload)
            ->assertStatus(422)
            ->assertJson([
                'message' => 'At least one of the following fields must be provided: placement_curver, placement_allver, difficulty, botb_difficulty, remake_of',
                'errors' => [
                    'meta_fields' => ['At least one of the following fields must be provided: placement_curver, placement_allver, difficulty, botb_difficulty, remake_of'],
                ],
            ]);
    }

    /**
     * Update from valid value to another valid value.
     * Should NOT dispatch accept submission job (job only on null -> value transition).
     */
    #[Group('update')]
    #[Group('maps')]
    public function test_update_map_from_value_to_value_does_not_dispatch_job(): void
    {
        $this->markTestIncomplete('Feature not yet implemented: no job dispatched when updating from one valid value to another');
    }
}
