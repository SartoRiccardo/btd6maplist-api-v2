<?php

namespace Tests\Feature\Completions;

use App\Constants\FormatConstants;
use App\Jobs\SendCompletionSubmissionWebhookJob;
use App\Models\Completion;
use App\Models\Format;
use App\Models\Map;
use App\Models\MapListMeta;
use App\Services\Discord\DiscordWebhookClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use PHPUnit\Metadata\Group;

class WebhookSubmissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DiscordWebhookClient::fake(true, '123456789');
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        DiscordWebhookClient::clearFake();
        parent::tearDown();
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

    // ========== SUBMISSION WEBHOOK TESTS ==========

    #[Group('webhook')]
    #[Group('completions')]
    public function test_store_completion_submission_dispatches_job(): void
    {
        Bus::fake();

        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['create:completion_submission']]);
        $map = $this->createMapForFormat(FormatConstants::MAPLIST);
        $format = Format::find(FormatConstants::MAPLIST);
        $format->run_submission_wh = 'https://discord.com/api/webhooks/test/webhook';
        $format->save();

        $this->actingAs($user, 'discord')
            ->postJson('/api/completions/submit', [
                'map' => $map->code,
                'format_id' => $format->id,
                'players' => [$user->discord_id],
                'proof_images' => $this->createProofImages(),
            ])
            ->assertStatus(201);

        Bus::assertDispatched(SendCompletionSubmissionWebhookJob::class);
    }

    #[Group('webhook')]
    #[Group('completions')]
    public function test_completion_job_stores_metadata_correctly(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['create:completion_submission']]);
        $map = $this->createMapForFormat(FormatConstants::MAPLIST);
        $format = Format::find(FormatConstants::MAPLIST);
        $format->run_submission_wh = 'https://discord.com/api/webhooks/test/webhook';
        $format->save();

        $response = $this->actingAs($user, 'discord')
            ->postJson('/api/completions/submit', [
                'map' => $map->code,
                'format_id' => $format->id,
                'players' => [$user->discord_id],
                'proof_images' => $this->createProofImages(),
            ]);

        $completionId = $response->json('id');
        $completion = Completion::find($completionId);

        $this->assertNotNull($completion, 'Completion should exist');
        $this->assertNotNull($completion->wh_msg_id, 'wh_msg_id should be set');
        $this->assertNotNull($completion->wh_data, 'wh_data should be set');

        $payload = json_decode($completion->wh_data, true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('embeds', $payload);
        $this->assertCount(1, $payload['embeds']);
    }
}
