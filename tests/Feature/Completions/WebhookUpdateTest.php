<?php

namespace Tests\Feature\Completions;

use App\Constants\FormatConstants;
use App\Jobs\UpdateCompletionWebhookJob;
use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\Format;
use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\User;
use App\Services\Discord\DiscordWebhookClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use PHPUnit\Metadata\Group;

class WebhookUpdateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
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

    // Helper to create a completion with webhook data
    protected function createCompletionWithWebhook(int $formatId, ?string $acceptedBy = null): array
    {
        $map = $this->createMapForFormat($formatId);
        $format = Format::find($formatId);
        $format->run_submission_wh = 'https://discord.com/api/webhooks/test/webhook';
        $format->save();

        $user = User::factory()->create();
        $completion = Completion::factory()->create([
            'map_code' => $map->code,
            'wh_msg_id' => '123456',
            'wh_data' => json_encode(['embeds' => [['color' => 0x1e88e5]]]),
        ]);

        $meta = CompletionMeta::factory()->create([
            'completion_id' => $completion->id,
            'format_id' => $formatId,
            'accepted_by_id' => $acceptedBy,
        ]);

        $meta->players()->attach($user->discord_id);

        return [$completion, $meta, $format, $user];
    }

    // ========== ACCEPTANCE WEBHOOK TESTS ==========

    #[Group('webhook')]
    #[Group('completions')]
    public function test_put_completion_dispatches_green_job_on_acceptance(): void
    {
        Bus::fake();

        $admin = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);
        [$completion, $meta, $format, $player] = $this->createCompletionWithWebhook(FormatConstants::MAPLIST, acceptedBy: null);

        $this->actingAs($admin, 'discord')
            ->putJson("/api/completions/{$completion->id}", [
                'format_id' => $format->id,
                'players' => [$player->discord_id],
                'accept' => true,
            ])
            ->assertStatus(204);

        Bus::assertDispatched(UpdateCompletionWebhookJob::class, function ($job) use ($completion) {
            $this->assertEquals($completion->id, $job->completionId);
            $this->assertFalse($job->fail);
            return true;
        });
    }

    #[Group('webhook')]
    #[Group('completions')]
    public function test_put_completion_skips_job_if_already_accepted(): void
    {
        Bus::fake();

        $admin = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);
        $originalAccepter = '987654321098765432';
        [$completion, $_meta, $format, $player] = $this->createCompletionWithWebhook(FormatConstants::MAPLIST, acceptedBy: $originalAccepter);

        $this->actingAs($admin, 'discord')
            ->putJson("/api/completions/{$completion->id}", [
                'format_id' => $format->id,
                'players' => [$player->discord_id],
                'accept' => true,
            ])
            ->assertStatus(204);

        Bus::assertNotDispatched(UpdateCompletionWebhookJob::class);
    }

    #[Group('webhook')]
    #[Group('completions')]
    public function test_put_completion_skips_job_if_still_pending(): void
    {
        Bus::fake();

        $admin = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);
        [$completion, $meta, $format, $player] = $this->createCompletionWithWebhook(FormatConstants::MAPLIST, acceptedBy: null);

        $this->actingAs($admin, 'discord')
            ->putJson("/api/completions/{$completion->id}", [
                'format_id' => $format->id,
                'players' => [$player->discord_id],
                'accept' => false, // Still pending
            ])
            ->assertStatus(204);

        Bus::assertNotDispatched(UpdateCompletionWebhookJob::class);
    }

    #[Group('webhook')]
    #[Group('completions')]
    public function test_put_completion_skips_job_if_no_webhook_data(): void
    {
        Bus::fake();

        $admin = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);
        $map = $this->createMapForFormat(FormatConstants::MAPLIST);
        $format = Format::find(FormatConstants::MAPLIST);
        $format->run_submission_wh = 'https://discord.com/api/webhooks/test/webhook';
        $format->save();

        $player = User::factory()->create();
        $completion = Completion::factory()->create([
            'map_code' => $map->code,
            'wh_msg_id' => null, // No webhook data
        ]);

        $meta = CompletionMeta::factory()->create([
            'completion_id' => $completion->id,
            'format_id' => $format->id,
            'accepted_by_id' => null,
        ]);

        $meta->players()->attach($player->discord_id);

        $this->actingAs($admin, 'discord')
            ->putJson("/api/completions/{$completion->id}", [
                'format_id' => $format->id,
                'players' => [$player->discord_id],
                'accept' => true,
            ])
            ->assertStatus(204);

        Bus::assertNotDispatched(UpdateCompletionWebhookJob::class);
    }

    // ========== DELETION WEBHOOK TESTS ==========

    #[Group('webhook')]
    #[Group('completions')]
    public function test_delete_pending_completion_dispatches_red_job(): void
    {
        Bus::fake();

        $admin = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);
        [$completion, $meta, $format, $player] = $this->createCompletionWithWebhook(FormatConstants::MAPLIST, acceptedBy: null);

        $this->actingAs($admin, 'discord')
            ->deleteJson("/api/completions/{$completion->id}")
            ->assertStatus(204);

        Bus::assertDispatched(UpdateCompletionWebhookJob::class, function ($job) use ($completion) {
            $this->assertEquals($completion->id, $job->completionId);
            $this->assertTrue($job->fail);
            return true;
        });
    }

    #[Group('webhook')]
    #[Group('completions')]
    public function test_delete_accepted_completion_skips_job(): void
    {
        Bus::fake();

        $admin = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);
        $originalAccepter = '987654321098765432';
        [$completion, $meta, $format, $player] = $this->createCompletionWithWebhook(FormatConstants::MAPLIST, acceptedBy: $originalAccepter);

        $this->actingAs($admin, 'discord')
            ->deleteJson("/api/completions/{$completion->id}")
            ->assertStatus(204);

        Bus::assertNotDispatched(UpdateCompletionWebhookJob::class);
    }

    #[Group('webhook')]
    #[Group('completions')]
    public function test_delete_completion_skips_job_if_no_webhook_data(): void
    {
        Bus::fake();

        $admin = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:completion']]);
        $map = $this->createMapForFormat(FormatConstants::MAPLIST);
        $format = Format::find(FormatConstants::MAPLIST);
        $format->run_submission_wh = 'https://discord.com/api/webhooks/test/webhook';
        $format->save();

        $player = User::factory()->create();
        $completion = Completion::factory()->create([
            'map_code' => $map->code,
            'wh_msg_id' => null, // No webhook data
        ]);

        $meta = CompletionMeta::factory()->create([
            'completion_id' => $completion->id,
            'format_id' => $format->id,
            'accepted_by_id' => null,
        ]);

        $meta->players()->attach($player->discord_id);

        $this->actingAs($admin, 'discord')
            ->deleteJson("/api/completions/{$completion->id}")
            ->assertStatus(204);

        Bus::assertNotDispatched(UpdateCompletionWebhookJob::class);
    }
}
