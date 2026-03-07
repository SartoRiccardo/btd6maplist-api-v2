<?php

namespace Tests\Feature\MapSubmissions;

use App\Constants\FormatConstants;
use App\Jobs\DeleteMapSubmissionWebhookJob;
use App\Jobs\SendMapSubmissionWebhookJob;
use App\Models\Format;
use App\Models\Map;
use App\Models\MapSubmission;
use App\Models\User;
use App\Services\NinjaKiwi\NinjaKiwiApiClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use PHPUnit\Metadata\Group;

class WebhookTest extends TestCase
{
    protected function tearDown(): void
    {
        NinjaKiwiApiClient::clearFake();
        parent::tearDown();
    }

    // ========== STORE WEBHOOK TESTS ==========

    #[Group('webhook')]
    #[Group('map_submissions')]
    public function test_store_submission_dispatches_webhook_job(): void
    {
        Bus::fake();

        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['create:map_submission']]);
        $map = Map::factory()->create();
        $format = Format::find(FormatConstants::MAPLIST);
        $format->map_submission_status = 'open';
        $format->map_submission_wh = 'https://discord.com/api/webhooks/test/webhook';
        $format->save();

        NinjaKiwiApiClient::fakeMapExists([$map->code => true]);
        Storage::fake('public');

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps/submissions', [
                'code' => $map->code,
                'format_id' => $format->id,
                'proposed' => 0,
                'completion_proof' => UploadedFile::fake()->image('proof.jpg'),
            ])
            ->assertStatus(201);

        Bus::assertDispatched(SendMapSubmissionWebhookJob::class);
    }

    #[Group('webhook')]
    #[Group('map_submissions')]
    public function test_store_submission_dispatches_webhook_job_even_without_webhook_configured(): void
    {
        Bus::fake();

        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['create:map_submission']]);
        $map = Map::factory()->create();
        $format = Format::find(FormatConstants::MAPLIST);
        $format->map_submission_status = 'open';
        $format->map_submission_wh = null; // No webhook configured
        $format->save();

        NinjaKiwiApiClient::fakeMapExists([$map->code => true]);
        Storage::fake('public');

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps/submissions', [
                'code' => $map->code,
                'format_id' => $format->id,
                'proposed' => 0,
                'completion_proof' => UploadedFile::fake()->image('proof.jpg'),
            ])
            ->assertStatus(201);

        // Job should still be dispatched (it will check for webhook internally)
        Bus::assertDispatched(SendMapSubmissionWebhookJob::class);
    }

    // ========== DELETE WEBHOOK TESTS ==========

    #[Group('webhook')]
    #[Group('map_submissions')]
    public function test_delete_submission_dispatches_cleanup_job(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $format = Format::find(FormatConstants::MAPLIST);
        $format->map_submission_wh = 'https://discord.com/api/webhooks/test/webhook';
        $format->save();

        $submission = MapSubmission::factory()
            ->pending()
            ->create([
                'submitter_id' => $user->discord_id,
                'format_id' => $format->id,
                'wh_msg_id' => '123456',
            ]);

        Storage::fake('public');

        $this->actingAs($user, 'discord')
            ->deleteJson("/api/maps/submissions/{$submission->id}")
            ->assertStatus(204);

        Bus::assertDispatched(DeleteMapSubmissionWebhookJob::class, function ($job) use ($format) {
            $this->assertEquals($format->map_submission_wh, $job->webhookUrl, 'Webhook URL does not match');
            $this->assertEquals('123456', $job->messageId, 'Message ID does not match');
            return true;
        });
    }

    #[Group('webhook')]
    #[Group('map_submissions')]
    public function test_delete_submission_skips_job_if_no_webhook_data(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $format = Format::factory()->create([
            'map_submission_wh' => 'https://discord.com/api/webhooks/test/webhook',
        ]);

        $submission = MapSubmission::factory()
            ->pending()
            ->create([
                'submitter_id' => $user->discord_id,
                'format_id' => $format->id,
                'wh_msg_id' => null, // No webhook message ID
            ]);

        Storage::fake('public');

        $this->actingAs($user, 'discord')
            ->deleteJson("/api/maps/submissions/{$submission->id}")
            ->assertStatus(204);

        Bus::assertNotDispatched(DeleteMapSubmissionWebhookJob::class);
    }

    #[Group('webhook')]
    #[Group('map_submissions')]
    public function test_delete_submission_skips_job_if_format_has_no_webhook(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $format = Format::find(FormatConstants::MAPLIST);
        $format->map_submission_wh = null; // No webhook configured
        $format->save();

        $submission = MapSubmission::factory()
            ->pending()
            ->create([
                'submitter_id' => $user->discord_id,
                'format_id' => $format->id,
                'wh_msg_id' => '123456',
            ]);

        Storage::fake('public');

        $this->actingAs($user, 'discord')
            ->deleteJson("/api/maps/submissions/{$submission->id}")
            ->assertStatus(204);

        Bus::assertNotDispatched(DeleteMapSubmissionWebhookJob::class);
    }
}
