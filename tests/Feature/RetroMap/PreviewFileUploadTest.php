<?php

namespace Tests\Feature\RetroMap;

use App\Models\RetroGame;
use App\Models\RetroMap;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PreviewFileUploadTest extends TestCase
{
    // POST/PUT /retro-maps — preview_file Upload
    // Either preview_url or preview_file must be provided. File stored to public/retro_map_previews/.

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function userWithPermissions(array $perms): \App\Models\User
    {
        return $this->createUserWithPermissions($perms);
    }

    private function basePayload(int $retroGameId): array
    {
        return [
            'name' => 'Test Retro Map',
            'sort_order' => 1,
            'retro_game_id' => $retroGameId,
        ];
    }

    // POST (create)

    public function test_creating_with_preview_url_only_works_as_before(): void
    {
        $user = $this->userWithPermissions([null => ['create:retro_map']]);
        $game = RetroGame::factory()->create();

        $response = $this->actingAs($user, 'discord')
            ->postJson('/api/maps/retro', array_merge($this->basePayload($game->id), [
                'preview_url' => 'https://example.com/preview.png',
            ]))
            ->assertStatus(201)
            ->json();

        $retroMap = RetroMap::find($response['id']);
        $this->assertEquals('https://example.com/preview.png', $retroMap->preview_url);
    }

    public function test_creating_with_preview_file_valid_jpg_stores_file_and_returns_public_url(): void
    {
        $user = $this->userWithPermissions([null => ['create:retro_map']]);
        $game = RetroGame::factory()->create();

        $response = $this->actingAs($user, 'discord')
            ->postJson('/api/maps/retro', array_merge($this->basePayload($game->id), [
                'preview_file' => UploadedFile::fake()->image('photo.jpg')->size(100),
            ]))
            ->assertStatus(201)
            ->json();

        $retroMap = RetroMap::find($response['id']);
        $this->assertNotNull($retroMap->preview_url);
        Storage::disk('public')->assertExists(
            'retro_map_previews/' . basename(parse_url($retroMap->preview_url, PHP_URL_PATH))
        );
    }

    public function test_stored_filename_is_a_uuid_with_the_files_extension(): void
    {
        $user = $this->userWithPermissions([null => ['create:retro_map']]);
        $game = RetroGame::factory()->create();

        $response = $this->actingAs($user, 'discord')
            ->postJson('/api/maps/retro', array_merge($this->basePayload($game->id), [
                'preview_file' => UploadedFile::fake()->image('photo.jpg')->size(100),
            ]))
            ->assertStatus(201)
            ->json();

        $retroMap = RetroMap::find($response['id']);
        $filename = basename(parse_url($retroMap->preview_url, PHP_URL_PATH));
        // UUID format: 8-4-4-4-12 chars separated by hyphens, plus .jpg extension
        $this->assertMatchesRegularExpression('/^[0-9a-f\-]{36}\.jpg$/', $filename);
    }

    public function test_returned_preview_url_points_to_the_stored_file(): void
    {
        $user = $this->userWithPermissions([null => ['create:retro_map']]);
        $game = RetroGame::factory()->create();

        $response = $this->actingAs($user, 'discord')
            ->postJson('/api/maps/retro', array_merge($this->basePayload($game->id), [
                'preview_file' => UploadedFile::fake()->image('photo.png')->size(100),
            ]))
            ->assertStatus(201)
            ->json();

        $retroMap = RetroMap::find($response['id']);
        $filename = 'retro_map_previews/' . basename(parse_url($retroMap->preview_url, PHP_URL_PATH));
        Storage::disk('public')->assertExists($filename);
    }

    public function test_create_neither_preview_url_nor_preview_file_provided_returns_422(): void
    {
        $user = $this->userWithPermissions([null => ['create:retro_map']]);
        $game = RetroGame::factory()->create();

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps/retro', $this->basePayload($game->id))
            ->assertStatus(422);
    }

    public function test_create_file_over_4_5_mb_returns_422(): void
    {
        $user = $this->userWithPermissions([null => ['create:retro_map']]);
        $game = RetroGame::factory()->create();

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps/retro', array_merge($this->basePayload($game->id), [
                'preview_file' => UploadedFile::fake()->image('large.jpg')->size(5000),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('preview_file');
    }

    public function test_create_unsupported_mime_type_returns_422(): void
    {
        $user = $this->userWithPermissions([null => ['create:retro_map']]);
        $game = RetroGame::factory()->create();

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps/retro', array_merge($this->basePayload($game->id), [
                'preview_file' => UploadedFile::fake()->create('file.pdf', 100, 'application/pdf'),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('preview_file');
    }

    public function test_create_both_preview_url_and_preview_file_provided_file_takes_precedence(): void
    {
        $user = $this->userWithPermissions([null => ['create:retro_map']]);
        $game = RetroGame::factory()->create();

        $response = $this->actingAs($user, 'discord')
            ->postJson('/api/maps/retro', array_merge($this->basePayload($game->id), [
                'preview_url' => 'https://example.com/old.png',
                'preview_file' => UploadedFile::fake()->image('photo.jpg')->size(100),
            ]))
            ->assertStatus(201)
            ->json();

        $retroMap = RetroMap::find($response['id']);
        // URL should not be the passed-in preview_url; it should point to the stored file
        $this->assertNotEquals('https://example.com/old.png', $retroMap->preview_url);
        Storage::disk('public')->assertExists(
            'retro_map_previews/' . basename(parse_url($retroMap->preview_url, PHP_URL_PATH))
        );
    }

    // PUT (update)

    public function test_updating_with_preview_file_replaces_old_preview_stored_as_id_ext(): void
    {
        $user = $this->userWithPermissions([null => ['edit:retro_map']]);
        $retroMap = RetroMap::factory()->create(['preview_url' => 'https://example.com/old.png']);

        $this->actingAs($user, 'discord')
            ->putJson("/api/maps/retro/{$retroMap->id}", [
                'name' => $retroMap->name,
                'sort_order' => $retroMap->sort_order,
                'retro_game_id' => $retroMap->retro_game_id,
                'preview_file' => UploadedFile::fake()->image('new.jpg')->size(100),
            ])
            ->assertStatus(204);

        // File stored as {id}.jpg
        Storage::disk('public')->assertExists("retro_map_previews/{$retroMap->id}.jpg");
        $retroMap->refresh();
        $this->assertNotEquals('https://example.com/old.png', $retroMap->preview_url);
    }

    public function test_updating_with_preview_url_only_does_not_create_a_file(): void
    {
        $user = $this->userWithPermissions([null => ['edit:retro_map']]);
        $retroMap = RetroMap::factory()->create(['preview_url' => 'https://example.com/old.png']);

        $this->actingAs($user, 'discord')
            ->putJson("/api/maps/retro/{$retroMap->id}", [
                'name' => $retroMap->name,
                'sort_order' => $retroMap->sort_order,
                'retro_game_id' => $retroMap->retro_game_id,
                'preview_url' => 'https://example.com/new.png',
            ])
            ->assertStatus(204);

        Storage::disk('public')->assertDirectoryEmpty('retro_map_previews');
        $retroMap->refresh();
        $this->assertEquals('https://example.com/new.png', $retroMap->preview_url);
    }

    public function test_update_file_over_4_5_mb_returns_422(): void
    {
        $user = $this->userWithPermissions([null => ['edit:retro_map']]);
        $retroMap = RetroMap::factory()->create();

        $this->actingAs($user, 'discord')
            ->putJson("/api/maps/retro/{$retroMap->id}", [
                'name' => $retroMap->name,
                'sort_order' => $retroMap->sort_order,
                'retro_game_id' => $retroMap->retro_game_id,
                'preview_file' => UploadedFile::fake()->image('large.jpg')->size(5000),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('preview_file');
    }

    public function test_update_unsupported_mime_type_returns_422(): void
    {
        $user = $this->userWithPermissions([null => ['edit:retro_map']]);
        $retroMap = RetroMap::factory()->create();

        $this->actingAs($user, 'discord')
            ->putJson("/api/maps/retro/{$retroMap->id}", [
                'name' => $retroMap->name,
                'sort_order' => $retroMap->sort_order,
                'retro_game_id' => $retroMap->retro_game_id,
                'preview_file' => UploadedFile::fake()->create('file.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('preview_file');
    }

    public function test_update_with_neither_preview_url_nor_preview_file_returns_422(): void
    {
        $user = $this->userWithPermissions([null => ['edit:retro_map']]);
        $retroMap = RetroMap::factory()->create();

        $this->actingAs($user, 'discord')
            ->putJson("/api/maps/retro/{$retroMap->id}", [
                'name' => $retroMap->name,
                'sort_order' => $retroMap->sort_order,
                'retro_game_id' => $retroMap->retro_game_id,
            ])
            ->assertStatus(422);
    }
}
