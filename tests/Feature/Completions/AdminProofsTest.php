<?php

namespace Tests\Feature\Completions;

use App\Constants\FormatConstants;
use App\Helpers\RequestHelpers;
use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\CompletionProof;
use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Metadata\Group;
use Tests\TestCase;
use Tests\Traits\TestsDiscordAuthMiddleware;

class AdminProofsTest extends TestCase
{
    use TestsDiscordAuthMiddleware;
    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        Storage::fake('public');
    }

    protected function endpoint(): string
    {
        return '/api/completions/1';
    }

    protected function method(): string
    {
        return 'PUT';
    }

    // ============================================================
    // Helpers
    // ============================================================

    protected function createCompletionWithProofs(
        int $formatId = FormatConstants::MAPLIST,
        int $originalImages = 0,
        int $adminImages = 0,
        bool $accepted = false,
    ): Completion {
        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create([
            'placement_curver' => 1,
            'placement_allver' => 1,
            'difficulty' => 1,
            'botb_difficulty' => 1,
        ]);

        $completion = Completion::factory()->create(['map_code' => $map->code]);

        $acceptor = $accepted ? User::factory()->create() : null;
        $player = User::factory()->create();

        $meta = CompletionMeta::factory()->for($completion)->create([
            'format_id' => $formatId,
            'accepted_by_id' => $acceptor?->discord_id,
            'created_on' => Carbon::now()->subSeconds(2),
        ]);
        $meta->players()->attach($player->discord_id);

        for ($i = 0; $i < $originalImages; $i++) {
            CompletionProof::factory()->image()->for($meta, 'completionMeta')->create([
                'run' => $completion->id,
                'is_added_by_admin' => false,
            ]);
        }

        for ($i = 0; $i < $adminImages; $i++) {
            CompletionProof::factory()->image()->adminAdded()->for($meta, 'completionMeta')->create([
                'run' => $completion->id,
            ]);
        }

        return $completion;
    }

    protected function adminUser(): User
    {
        return $this->createUserWithPermissions([
            FormatConstants::MAPLIST => ['edit:completion'],
        ]);
    }

    protected function basePayload(Completion $completion): array
    {
        $player = User::factory()->create();
        return [
            'format_id' => FormatConstants::MAPLIST,
            'players' => [$player->discord_id],
            'accept' => false,
        ];
    }

    // ============================================================
    // RequestHelpers::mergeFileAndUrlInputs
    // ============================================================

    #[Group('admin-proofs')]
    public function test_merge_files_only(): void
    {
        $file0 = UploadedFile::fake()->image('a.jpg');
        $file1 = UploadedFile::fake()->image('b.jpg');

        $request = Request::create('/test', 'PUT');
        $request->files->set('proofs', [$file0, $file1]);

        $result = RequestHelpers::mergeFileAndUrlInputs($request, 'proofs');

        $this->assertCount(2, $result);
        $this->assertSame($file0, $result[0]);
        $this->assertSame($file1, $result[1]);
    }

    #[Group('admin-proofs')]
    public function test_merge_urls_only(): void
    {
        $request = Request::create('/test', 'PUT', ['proofs' => ['https://a.com/1.jpg', 'https://b.com/2.jpg']]);

        $result = RequestHelpers::mergeFileAndUrlInputs($request, 'proofs');

        $this->assertCount(2, $result);
        $this->assertSame('https://a.com/1.jpg', $result[0]);
        $this->assertSame('https://b.com/2.jpg', $result[1]);
    }

    #[Group('admin-proofs')]
    public function test_merge_interleaved_preserves_order(): void
    {
        $file0 = UploadedFile::fake()->image('a.jpg');
        $file2 = UploadedFile::fake()->image('c.jpg');

        $request = Request::create('/test', 'PUT', ['proofs' => [1 => 'https://b.com/2.jpg']]);
        $request->files->set('proofs', [0 => $file0, 2 => $file2]);

        $result = RequestHelpers::mergeFileAndUrlInputs($request, 'proofs');

        $this->assertCount(3, $result);
        $this->assertSame($file0, $result[0]);
        $this->assertSame('https://b.com/2.jpg', $result[1]);
        $this->assertSame($file2, $result[2]);
    }

    #[Group('admin-proofs')]
    public function test_merge_both_empty_returns_empty_array(): void
    {
        $request = Request::create('/test', 'PUT');

        $result = RequestHelpers::mergeFileAndUrlInputs($request, 'proofs');

        $this->assertSame([], $result);
    }

    // ============================================================
    // Sync logic
    // ============================================================

    #[Group('admin-proofs')]
    public function test_sync_wipes_admin_proofs_on_empty_array(): void
    {
        $completion = $this->createCompletionWithProofs(originalImages: 2, adminImages: 2);
        $user = $this->adminUser();
        $payload = array_merge($this->basePayload($completion), ['additional_image_proofs' => []]);

        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$completion->id}", $payload)
            ->assertStatus(204);

        $imgs = $this->actingAs($user, 'discord')
            ->getJson("/api/completions/{$completion->id}")
            ->json('subm_proof_img');

        $this->assertCount(2, $imgs);
        $this->assertFalse($imgs[0]['is_added_by_admin']);
        $this->assertFalse($imgs[1]['is_added_by_admin']);
    }

    #[Group('admin-proofs')]
    public function test_sync_wipes_admin_proofs_when_field_absent(): void
    {
        $completion = $this->createCompletionWithProofs(originalImages: 2, adminImages: 2);
        $user = $this->adminUser();

        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$completion->id}", $this->basePayload($completion))
            ->assertStatus(204);

        $imgs = $this->actingAs($user, 'discord')
            ->getJson("/api/completions/{$completion->id}")
            ->json('subm_proof_img');

        $this->assertCount(2, $imgs);
        $this->assertFalse($imgs[0]['is_added_by_admin']);
        $this->assertFalse($imgs[1]['is_added_by_admin']);
    }

    #[Group('admin-proofs')]
    public function test_sync_preserves_original_proofs(): void
    {
        $completion = $this->createCompletionWithProofs(originalImages: 3, adminImages: 2);
        $user = $this->adminUser();
        $payload = array_merge($this->basePayload($completion), ['additional_image_proofs' => []]);

        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$completion->id}", $payload)
            ->assertStatus(204);

        $imgs = $this->actingAs($user, 'discord')
            ->getJson("/api/completions/{$completion->id}")
            ->json('subm_proof_img');

        $this->assertCount(3, $imgs);
        foreach ($imgs as $img) {
            $this->assertFalse($img['is_added_by_admin']);
        }
    }

    #[Group('admin-proofs')]
    public function test_sync_replaces_admin_proofs_with_new_url_set(): void
    {
        $completion = $this->createCompletionWithProofs(adminImages: 2);
        $user = $this->adminUser();
        $payload = array_merge($this->basePayload($completion), [
            'additional_image_proofs' => ['https://example.com/new.jpg'],
        ]);

        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$completion->id}", $payload)
            ->assertStatus(204);

        $imgs = $this->actingAs($user, 'discord')
            ->getJson("/api/completions/{$completion->id}")
            ->json('subm_proof_img');

        $this->assertCount(1, $imgs);
        $this->assertSame('https://example.com/new.jpg', $imgs[0]['url']);
        $this->assertTrue($imgs[0]['is_added_by_admin']);
    }

    #[Group('admin-proofs')]
    public function test_sync_adds_proofs_when_none_existed(): void
    {
        $completion = $this->createCompletionWithProofs(originalImages: 1);
        $user = $this->adminUser();
        $payload = array_merge($this->basePayload($completion), [
            'additional_image_proofs' => ['https://example.com/admin.jpg'],
        ]);

        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$completion->id}", $payload)
            ->assertStatus(204);

        $imgs = $this->actingAs($user, 'discord')
            ->getJson("/api/completions/{$completion->id}")
            ->json('subm_proof_img');

        $this->assertCount(2, $imgs);
        $this->assertFalse($imgs[0]['is_added_by_admin']); // original first
        $this->assertTrue($imgs[1]['is_added_by_admin']);
        $this->assertSame('https://example.com/admin.jpg', $imgs[1]['url']);
    }

    #[Group('admin-proofs')]
    public function test_sync_stores_uploaded_file_and_creates_proof(): void
    {
        $completion = $this->createCompletionWithProofs();
        $user = $this->adminUser();

        $this->actingAs($user, 'discord')
            ->call('PUT', "/api/completions/{$completion->id}", $this->basePayload($completion), [], [
                'additional_image_proofs' => [UploadedFile::fake()->image('admin.jpg')->size(100)],
            ])
            ->assertStatus(204);

        $imgs = $this->actingAs($user, 'discord')
            ->getJson("/api/completions/{$completion->id}")
            ->json('subm_proof_img');

        $this->assertCount(1, $imgs);
        $this->assertTrue($imgs[0]['is_added_by_admin']);
        $this->assertStringContainsString("completion_proofs/{$completion->id}/admin_", $imgs[0]['url']);

        // Verify the file was actually written to storage (not just a URL string)
        $files = Storage::disk('public')->files("completion_proofs/{$completion->id}");
        $this->assertCount(1, $files);
    }

    // ============================================================
    // Validation
    // ============================================================

    #[Group('admin-proofs')]
    public function test_invalid_url_string_returns_422(): void
    {
        $completion = $this->createCompletionWithProofs(originalImages: 1);
        $user = $this->adminUser();
        $payload = array_merge($this->basePayload($completion), [
            'additional_image_proofs' => ['not-a-url'],
        ]);

        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$completion->id}", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['additional_image_proofs.0']);

        // State must be unchanged — original proof still there, no admin proofs added
        $imgs = $this->actingAs($user, 'discord')
            ->getJson("/api/completions/{$completion->id}")
            ->json('subm_proof_img');

        $this->assertCount(1, $imgs);
        $this->assertFalse($imgs[0]['is_added_by_admin']);
    }

    #[Group('admin-proofs')]
    public function test_non_image_mime_returns_422(): void
    {
        $completion = $this->createCompletionWithProofs(originalImages: 1);
        $user = $this->adminUser();

        $this->actingAs($user, 'discord')
            ->call('PUT', "/api/completions/{$completion->id}", $this->basePayload($completion), [], [
                'additional_image_proofs' => [UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')],
            ])
            ->assertStatus(422);

        $imgs = $this->actingAs($user, 'discord')
            ->getJson("/api/completions/{$completion->id}")
            ->json('subm_proof_img');

        $this->assertCount(1, $imgs);
        $this->assertFalse($imgs[0]['is_added_by_admin']);
    }

    #[Group('admin-proofs')]
    public function test_oversized_file_returns_422(): void
    {
        $completion = $this->createCompletionWithProofs(originalImages: 1);
        $user = $this->adminUser();

        $this->actingAs($user, 'discord')
            ->call('PUT', "/api/completions/{$completion->id}", $this->basePayload($completion), [], [
                'additional_image_proofs' => [UploadedFile::fake()->image('big.jpg')->size(11000)],
            ])
            ->assertStatus(422);

        $imgs = $this->actingAs($user, 'discord')
            ->getJson("/api/completions/{$completion->id}")
            ->json('subm_proof_img');

        $this->assertCount(1, $imgs);
        $this->assertFalse($imgs[0]['is_added_by_admin']);
    }

    #[Group('admin-proofs')]
    public function test_too_many_proofs_returns_422(): void
    {
        $completion = $this->createCompletionWithProofs();
        $user = $this->adminUser();
        $payload = array_merge($this->basePayload($completion), [
            'additional_image_proofs' => array_fill(0, 11, 'https://example.com/img.jpg'),
        ]);

        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$completion->id}", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['additional_image_proofs']);
    }

    #[Group('admin-proofs')]
    public function test_subm_proof_img_returns_objects_with_url_and_flag(): void
    {
        $completion = $this->createCompletionWithProofs(originalImages: 1);
        $seededUrl = CompletionProof::where('run', $completion->id)->value('proof_url');

        $user = $this->adminUser();
        $imgs = $this->actingAs($user, 'discord')
            ->getJson("/api/completions/{$completion->id}")
            ->assertStatus(200)
            ->json('subm_proof_img');

        $this->assertCount(1, $imgs);
        $this->assertSame($seededUrl, $imgs[0]['url']);
        $this->assertFalse($imgs[0]['is_added_by_admin']);
    }

    #[Group('admin-proofs')]
    public function test_original_proof_has_is_added_by_admin_false(): void
    {
        $completion = $this->createCompletionWithProofs(originalImages: 1);
        $user = $this->adminUser();

        $imgs = $this->actingAs($user, 'discord')
            ->getJson("/api/completions/{$completion->id}")
            ->assertStatus(200)
            ->json('subm_proof_img');

        $this->assertFalse($imgs[0]['is_added_by_admin']);
    }

    #[Group('admin-proofs')]
    public function test_admin_proof_has_is_added_by_admin_true(): void
    {
        $completion = $this->createCompletionWithProofs(adminImages: 1);
        $user = $this->adminUser();

        $imgs = $this->actingAs($user, 'discord')
            ->getJson("/api/completions/{$completion->id}")
            ->assertStatus(200)
            ->json('subm_proof_img');

        $this->assertTrue($imgs[0]['is_added_by_admin']);
    }

    #[Group('admin-proofs')]
    public function test_original_proofs_appear_before_admin_proofs(): void
    {
        $completion = $this->createCompletionWithProofs(originalImages: 2, adminImages: 2);
        $user = $this->adminUser();

        $imgs = $this->actingAs($user, 'discord')
            ->getJson("/api/completions/{$completion->id}")
            ->assertStatus(200)
            ->json('subm_proof_img');

        $this->assertCount(4, $imgs);
        $this->assertFalse($imgs[0]['is_added_by_admin']);
        $this->assertFalse($imgs[1]['is_added_by_admin']);
        $this->assertTrue($imgs[2]['is_added_by_admin']);
        $this->assertTrue($imgs[3]['is_added_by_admin']);
    }
}
