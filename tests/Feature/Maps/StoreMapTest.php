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

class StoreMapTest extends TestCase
{
    use TestsDiscordAuthMiddleware;

    protected function endpoint(): string
    {
        return '/api/maps';
    }

    protected function method(): string
    {
        return 'POST';
    }

    protected function requestData(): array
    {
        return [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
        ];
    }

    protected function expectedSuccessStatusCode(): int
    {
        return 201;
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_no_edit_map_permission_returns_403(): void
    {
        $user = $this->createUserWithPermissions([]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(403)
            ->assertJson(['message' => 'Forbidden - You do not have permission to create maps']);
    }

    /**
     * @dataProvider storeMapFiltersByPermissionProvider
     */
    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_filters_by_permission(int|null $formatId, string $field): void
    {
        $retroMap = RetroMap::factory()->create();
        $user = $this->createUserWithPermissions([$formatId => ['edit:map']]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'placement_curver' => 1,
            'placement_allver' => 1,
            'difficulty' => 3,
            'optimal_heros' => ['Quincy'],
            'botb_difficulty' => 2,
            'remake_of' => $retroMap->id,
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(201)
            ->assertJson(['code' => 'TESTCODE']);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/TESTCODE')
            ->assertStatus(200)
            ->json();

        // The field they have permission for should be set
        $this->assertEquals($payload[$field], $actual[$field]);

        // All other meta fields should be null
        $metaFields = ['placement_curver', 'placement_allver', 'difficulty', 'botb_difficulty', 'remake_of'];
        foreach ($metaFields as $metaField) {
            if ($metaField !== $field) {
                $this->assertNull($actual[$metaField], "Field {$metaField} should be null for format " . ($formatId ?? 'null'));
            }
        }
    }

    public static function storeMapFiltersByPermissionProvider(): array
    {
        return [
            'maplist permission sets placement_curver' => [FormatConstants::MAPLIST, 'placement_curver'],
            'maplist all versions permission sets placement_allver' => [FormatConstants::MAPLIST_ALL_VERSIONS, 'placement_allver'],
            'expert list permission sets difficulty' => [FormatConstants::EXPERT_LIST, 'difficulty'],
            'best of the best permission sets botb_difficulty' => [FormatConstants::BEST_OF_THE_BEST, 'botb_difficulty'],
            'nostalgia pack sets remake_of' => [FormatConstants::NOSTALGIA_PACK, 'remake_of'],
        ];
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_global_permission_sets_all_meta_fields(): void
    {
        $retroMap = RetroMap::factory()->create();
        $user = $this->createUserWithPermissions([null => ['edit:map']]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
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
            ->postJson('/api/maps', $payload)
            ->assertStatus(201)
            ->assertJson(['code' => 'TESTCODE']);

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

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_empty_payload_returns_all_required_field_errors(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $actual = $this->actingAs($user, 'discord')
            ->postJson('/api/maps', [])
            ->assertStatus(422)
            ->json();

        $expected = ['code', 'name'];
        $actualKeys = array_keys($actual['errors']);
        sort($expected);
        sort($actualKeys);
        $this->assertEquals($expected, $actualKeys);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_invalid_code_returns_error(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $payload = [
            'code' => 'TOOLONGCODE123',
            'name' => 'Test Map',
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_duplicate_code_returns_error(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);
        $existingMap = Map::factory()->create();

        $payload = [
            'code' => $existingMap->code,
            'name' => 'Test Map',
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_invalid_name_returns_error(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => str_repeat('a', 256),
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_placement_curver_exceeding_max_returns_error_with_correct_max_value(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        // Create 5 maps with placement_curver set
        $maps = Map::factory()->count(5)->create();
        MapListMeta::factory()
            ->count(5)
            ->sequence(fn($sequence) => [
                'code' => $maps[$sequence->index]->code,
                'placement_curver' => $sequence->index + 1,
            ])
            ->create();

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'placement_curver' => 100,
        ];

        $errors = $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422)
            ->json('errors');

        $this->assertNotEmpty($errors['placement_curver']);
        $this->assertStringContainsString('6', $errors['placement_curver'][0]);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_placement_allver_exceeding_max_returns_error_with_correct_max_value(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST_ALL_VERSIONS => ['edit:map']]);

        // Create 5 maps with placement_allver set
        $maps = Map::factory()->count(5)->create();
        MapListMeta::factory()
            ->count(5)
            ->sequence(fn($sequence) => [
                'code' => $maps[$sequence->index]->code,
                'placement_allver' => $sequence->index + 1,
            ])
            ->create();

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'placement_allver' => 100,
        ];

        $errors = $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422)
            ->json('errors');

        $this->assertNotEmpty($errors['placement_allver']);
        $this->assertStringContainsString('6', $errors['placement_allver'][0]);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_multiple_invalid_fields_returns_all_errors(): void
    {
        $user = $this->createUserWithPermissions([null => ['edit:map']]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
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
            ->postJson('/api/maps', $payload)
            ->assertStatus(422)
            ->json();

        $expected = ['r6_start', 'map_preview_url', 'map_notes', 'placement_curver', 'placement_allver', 'difficulty', 'botb_difficulty', 'remake_of', 'optimal_heros', 'creators.0.role', 'creators.0.user_id', 'creators.1.role', 'creators.1.user_id', 'verifiers.0.version', 'verifiers.0.user_id', 'verifiers.1.version', 'verifiers.1.user_id'];
        $actualKeys = array_keys($actual['errors']);
        sort($expected);
        sort($actualKeys);
        $this->assertEquals($expected, $actualKeys);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_optimal_heros_too_many_items_returns_error(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'optimal_heros' => ['Quincy', 'Gwendolin', 'Striker', 'Obyn', 'Captain Churchill', 'Benjamin', 'Etienne', 'Sauda', 'Adora', 'Brickell', 'Geraldo'],
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['optimal_heros']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_optimal_heros_item_too_long_returns_error(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'optimal_heros' => [str_repeat('a', 51)],
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['optimal_heros.0']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_optimal_heros_with_duplicates_returns_error(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'optimal_heros' => ['Quincy', 'Gwendolin', 'quincy'], // Case-insensitive duplicate
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['optimal_heros.2']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_happy_path_with_admin_sets_everything(): void
    {
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
            'code' => 'TESTCODE',
            'name' => 'Test Map',
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
            ->postJson('/api/maps', $payload)
            ->assertStatus(201)
            ->assertJson(['code' => 'TESTCODE']);

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
            'name' => 'Test Map',
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

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_at_position_n_shifts_other_maps_by_one(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        // Create 5 maps with placement_curver set
        $maps = Map::factory()->count(5)->create();
        MapListMeta::factory()
            ->count(5)
            ->sequence(fn($sequence) => [
                'code' => $maps[$sequence->index]->code,
                'placement_curver' => $sequence->index + 1,
            ])
            ->create();

        // Insert new map at position 3
        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'placement_curver' => 3,
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(201);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps?format_id=' . FormatConstants::MAPLIST)
            ->assertStatus(200)
            ->json('data');

        $placements = collect($actual)->pluck('placement_curver', 'code');

        // Original maps at positions 1-2 stay the same
        $this->assertEquals(1, $placements[$maps[0]->code]);
        $this->assertEquals(2, $placements[$maps[1]->code]);

        // New map is at position 3
        $this->assertEquals(3, $placements['TESTCODE']);

        // Original maps at positions 3-5 shifted to 4-6
        $this->assertEquals(4, $placements[$maps[2]->code]);
        $this->assertEquals(5, $placements[$maps[3]->code]);
        $this->assertEquals(6, $placements[$maps[4]->code]);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_remake_of_steals_from_existing_remake(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::NOSTALGIA_PACK => ['edit:map']]);

        $retroMap = RetroMap::factory()->create();
        $existingMap = Map::factory()->create();
        MapListMeta::factory()->for($existingMap)->create(['remake_of' => $retroMap->id]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'remake_of' => $retroMap->id,
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(201)
            ->assertJson(['code' => 'TESTCODE']);

        // New map has the remake_of
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

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_empty_creators_and_verifiers_works(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'placement_curver' => 1,
            'creators' => [],
            'verifications' => [],
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(201)
            ->assertJson(['code' => 'TESTCODE']);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/TESTCODE')
            ->assertStatus(200)
            ->json();

        $this->assertIsArray($actual['creators']);
        $this->assertEmpty($actual['creators']);
        $this->assertIsArray($actual['verifications']);
        $this->assertEmpty($actual['verifications']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_without_creators_and_verifiers_works(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'placement_curver' => 1,
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(201)
            ->assertJson(['code' => 'TESTCODE']);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/TESTCODE')
            ->assertStatus(200)
            ->json();

        $this->assertIsArray($actual['creators']);
        $this->assertEmpty($actual['creators']);
        $this->assertIsArray($actual['verifications']);
        $this->assertEmpty($actual['verifications']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_creators_without_role_returns_422(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);
        $creator = User::factory()->create();

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'creators' => [
                ['user_id' => $creator->discord_id],
            ],
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['creators.0.role']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_creators_with_role_works(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);
        $creator = User::factory()->create();

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'placement_curver' => 1,
            'creators' => [
                ['user_id' => $creator->discord_id, 'role' => 'Gameplay'],
            ],
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(201);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/TESTCODE')
            ->assertStatus(200)
            ->json();

        $this->assertCount(1, $actual['creators']);
        $this->assertEquals($creator->discord_id, $actual['creators'][0]['user_id']);
        $this->assertEquals('Gameplay', $actual['creators'][0]['role']);
    }

    #[Group('store')]
    #[Group('maps')]
    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_verifiers_without_version_returns_422(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);
        $verifier = User::factory()->create();

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'verifiers' => [
                ['user_id' => $verifier->discord_id],
            ],
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['verifiers.0.version']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_creators_with_various_roles_works(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);
        $creator1 = User::factory()->create();
        $creator2 = User::factory()->create();
        $creator3 = User::factory()->create();

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'placement_curver' => 1,
            'creators' => [
                ['user_id' => $creator1->discord_id, 'role' => 'Gameplay'],
                ['user_id' => $creator2->discord_id, 'role' => null],
                ['user_id' => $creator3->discord_id, 'role' => 'Design'],
            ],
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(201);

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

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_duplicate_creator_returns_error(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);
        $creator = User::factory()->create();

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'creators' => [
                ['user_id' => $creator->discord_id],
                ['user_id' => $creator->discord_id],
            ],
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['creators.1.user_id']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_verifiers_same_user_different_versions_works(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);
        $verifier = User::factory()->create();
        $currentVersion = Config::loadVars(['current_btd6_ver'])->get('current_btd6_ver');

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'placement_curver' => 1,
            'verifiers' => [
                ['user_id' => $verifier->discord_id, 'version' => null],
                ['user_id' => $verifier->discord_id, 'version' => $currentVersion],
            ],
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(201);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/TESTCODE')
            ->assertStatus(200)
            ->json();

        $this->assertCount(2, $actual['verifications']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_duplicate_verifier_same_version_returns_error(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);
        $verifier = User::factory()->create();

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'verifiers' => [
                ['user_id' => $verifier->discord_id, 'version' => null],
                ['user_id' => $verifier->discord_id, 'version' => null],
            ],
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['verifiers.1.user_id']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_map_preview_file(): void
    {
        Storage::fake('public');

        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $file = UploadedFile::fake()->image('preview.jpg', 1024, 1024)->size(100);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'placement_curver' => 1,
            'custom_map_preview_file' => $file,
        ];

        $this->actingAs($user, 'discord')
            ->post('/api/maps', $payload)
            ->assertStatus(201);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/TESTCODE')
            ->assertStatus(200)
            ->json();

        $this->assertStringContainsString('/storage/map_previews/TESTCODE.jpg', $actual['map_preview_url']);
        Storage::disk('public')->assertExists('map_previews/TESTCODE.jpg');
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_both_preview_url_and_file_file_takes_precedence(): void
    {
        Storage::fake('public');

        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $file = UploadedFile::fake()->image('preview.png', 1024, 1024)->size(100);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'placement_curver' => 1,
            'map_preview_url' => 'https://example.com/old-preview.png',
            'custom_map_preview_file' => $file,
        ];

        $this->actingAs($user, 'discord')
            ->post('/api/maps', $payload)
            ->assertStatus(201);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/TESTCODE')
            ->assertStatus(200)
            ->json();

        // File should take precedence over URL
        $this->assertStringContainsString('/storage/map_previews/TESTCODE.png', $actual['map_preview_url']);
        $this->assertStringNotContainsString('https://example.com/old-preview.png', $actual['map_preview_url']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_invalid_image_extension_returns_422(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $file = UploadedFile::fake()->create('invalid.pdf', 100); // Not an image

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'custom_map_preview_file' => $file,
        ];

        $this->actingAs($user, 'discord')
            ->post('/api/maps', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['custom_map_preview_file']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_map_preview_file_too_large_returns_422(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $file = UploadedFile::fake()->image('huge.jpg', 1024, 1024)->size(4501);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'custom_map_preview_file' => $file,
        ];

        $this->actingAs($user, 'discord')
            ->post('/api/maps', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['custom_map_preview_file']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_r6_start_file(): void
    {
        Storage::fake('public');

        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $file = UploadedFile::fake()->image('r6_start.jpg', 1024, 1024)->size(100);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'placement_curver' => 1,
            'r6_start_file' => $file,
        ];

        $this->actingAs($user, 'discord')
            ->post('/api/maps', $payload)
            ->assertStatus(201);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/TESTCODE')
            ->assertStatus(200)
            ->json();

        $this->assertStringContainsString('/storage/r6_starts/TESTCODE.jpg', $actual['r6_start']);
        Storage::disk('public')->assertExists('r6_starts/TESTCODE.jpg');
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_both_r6_start_url_and_file_file_takes_precedence(): void
    {
        Storage::fake('public');

        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $file = UploadedFile::fake()->image('r6_start.png', 1024, 1024)->size(100);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'placement_curver' => 1,
            'r6_start' => 'https://example.com/old-r6-start.png',
            'r6_start_file' => $file,
        ];

        $this->actingAs($user, 'discord')
            ->post('/api/maps', $payload)
            ->assertStatus(201);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/TESTCODE')
            ->assertStatus(200)
            ->json();

        // File should take precedence over URL
        $this->assertStringContainsString('/storage/r6_starts/TESTCODE.png', $actual['r6_start']);
        $this->assertStringNotContainsString('https://example.com/old-r6-start.png', $actual['r6_start']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_invalid_r6_start_file_extension_returns_422(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $file = UploadedFile::fake()->create('invalid.pdf', 100); // Not an image or video

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'r6_start_file' => $file,
        ];

        $this->actingAs($user, 'discord')
            ->post('/api/maps', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['r6_start_file']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_r6_start_file_too_large_returns_422(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $file = UploadedFile::fake()->image('huge.jpg', 1024, 1024)->size(4501); // 4501KB = > 4.5MB

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'r6_start_file' => $file,
        ];

        $this->actingAs($user, 'discord')
            ->post('/api/maps', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['r6_start_file']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_duplicate_alias_returns_422(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'aliases' => ['TestAlias', 'testalias', 'AnotherAlias'],
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['aliases.1']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_alias_taken_by_existing_map_returns_422(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $existingMap = Map::factory()->create();
        MapListMeta::factory()->for($existingMap)->create();
        MapAlias::factory()->for($existingMap, 'map')->create(['alias' => 'ExistingAlias']);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'aliases' => ['ExistingAlias'],
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['aliases.0']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_alias_taken_by_deleted_map_works(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $existingMap = Map::factory()->create();
        MapListMeta::factory()->for($existingMap)->create(['deleted_on' => now()->subDay()]);
        MapAlias::factory()->for($existingMap, 'map')->create(['alias' => 'DeletedMapAlias']);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'placement_curver' => 1,
            'aliases' => ['DeletedMapAlias'],
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(201);

        $this->assertDatabaseHas('map_aliases', [
            'alias' => 'deletedmapalias',
            'map_code' => 'TESTCODE',
        ]);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_alias_case_insensitive_returns_422(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $existingMap = Map::factory()->create();
        MapListMeta::factory()->for($existingMap)->create();
        MapAlias::factory()->for($existingMap, 'map')->create(['alias' => 'MyAlias']);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'aliases' => ['myalias'], // Different case
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['aliases.0']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_nonexistent_creator_returns_422(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'creators' => [
                ['user_id' => '123456789012345678'], // Non-existent user
            ],
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['creators.0.user_id']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_nonexistent_verifier_returns_422(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'verifiers' => [
                ['user_id' => '123456789012345678'], // Non-existent user
            ],
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['verifiers.0.user_id']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_that_was_a_submission_dispatches_accept_submission_job(): void
    {
        $this->markTestIncomplete('Feature not yet implemented: dispatch accept submission job when storing a map that was a submission');
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_map_store_fails_if_all_meta_fields_are_null(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            // No meta fields provided
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422)
            ->assertJson([
                'message' => 'At least one of the following fields must be provided: placement_curver, placement_allver, difficulty, botb_difficulty, remake_of',
                'errors' => [
                    'meta_fields' => ['At least one of the following fields must be provided: placement_curver, placement_allver, difficulty, botb_difficulty, remake_of'],
                ],
            ]);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_map_store_succeeds_if_at_least_one_meta_field_is_provided(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'placement_curver' => 1,
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(201)
            ->assertJson(['code' => 'TESTCODE']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_map_store_succeeds_with_remake_of_only(): void
    {
        $retroMap = RetroMap::factory()->create();
        $user = $this->createUserWithPermissions([FormatConstants::NOSTALGIA_PACK => ['edit:map']]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'remake_of' => $retroMap->id,
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(201)
            ->assertJson(['code' => 'TESTCODE']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_map_store_fails_if_provided_field_is_not_in_user_permissions(): void
    {
        // User has EXPERT_LIST permission (controls difficulty), but provides placement_curver (MAPLIST permission)
        $user = $this->createUserWithPermissions([FormatConstants::EXPERT_LIST => ['edit:map']]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'placement_curver' => 1, // User doesn't have MAPLIST permission
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422)
            ->assertJson([
                'message' => 'At least one of the following fields must be provided: placement_curver, placement_allver, difficulty, botb_difficulty, remake_of',
                'errors' => [
                    'meta_fields' => ['At least one of the following fields must be provided: placement_curver, placement_allver, difficulty, botb_difficulty, remake_of'],
                ],
            ]);
    }

    /**
     * Map was submitted in another format, and we're setting a value for a different format.
     * Should NOT dispatch accept submission job.
     */
    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_from_different_format_submission_does_not_dispatch_job(): void
    {
        $this->markTestIncomplete('Feature not yet implemented: no job dispatched when setting value for format different from submission format');
    }
}
