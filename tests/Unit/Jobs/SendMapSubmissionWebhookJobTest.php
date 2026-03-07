<?php

namespace Tests\Unit\Jobs;

use App\Constants\DiscordColors;
use App\Constants\FormatConstants;
use App\Jobs\SendMapSubmissionWebhookJob;
use App\Models\Format;
use App\Models\Map;
use App\Models\MapSubmission;
use App\Models\RetroMap;
use App\Models\User;
use App\Services\Discord\DiscordWebhookClient;
use App\Services\NinjaKiwi\NinjaKiwiApiClient;
use Tests\TestCase;

class SendMapSubmissionWebhookJobTest extends TestCase
{
    private User $submitter;
    private Map $map;

    protected function setUp(): void
    {
        parent::setUp();
        $this->submitter = User::factory()->create();
        $this->map = Map::factory()->create([
            'code' => 'TESTCODE',
            'name' => 'Test Map Name',
        ]);
    }

    protected function tearDown(): void
    {
        DiscordWebhookClient::clearFake();
        NinjaKiwiApiClient::clearFake();
        parent::tearDown();
    }

    public function test_job_sends_webhook_and_saves_message_id(): void
    {
        $format = Format::find(FormatConstants::MAPLIST);
        $format->map_submission_wh = 'https://discord.com/api/webhooks/test/webhook';
        $format->save();

        $submission = MapSubmission::factory()->create([
            'code' => $this->map->code,
            'submitter_id' => $this->submitter->discord_id,
            'format_id' => $format->id,
            'proposed' => 1,
        ]);

        DiscordWebhookClient::fake(true);

        $job = new SendMapSubmissionWebhookJob($submission->id);
        $job->handle(app(DiscordWebhookClient::class));

        $submission->refresh();
        $this->assertNotNull($submission->wh_msg_id);
        $this->assertNotNull($submission->wh_data);
    }

    public function test_job_skips_if_no_webhook_configured(): void
    {
        $format = Format::find(FormatConstants::MAPLIST);
        $format->map_submission_wh = null;
        $format->save();

        $submission = MapSubmission::factory()->create([
            'code' => $this->map->code,
            'submitter_id' => $this->submitter->discord_id,
            'format_id' => $format->id,
        ]);

        DiscordWebhookClient::fake(true);

        $job = new SendMapSubmissionWebhookJob($submission->id);
        $job->handle(app(DiscordWebhookClient::class));

        $submission->refresh();
        $this->assertNull($submission->wh_msg_id);
        $this->assertNull($submission->wh_data);
    }

    public function test_job_handles_nonexistent_submission(): void
    {
        DiscordWebhookClient::fake(true);

        $job = new SendMapSubmissionWebhookJob(999999);
        $job->handle(app(DiscordWebhookClient::class));

        // Should not throw exception
        $this->assertTrue(true);
    }

    public function test_job_handles_nonexistent_map(): void
    {
        $format = Format::find(FormatConstants::MAPLIST);
        $format->map_submission_wh = 'https://discord.com/api/webhooks/test/webhook';
        $format->save();

        $submission = MapSubmission::factory()->create([
            'code' => 'NOEXIST',
            'submitter_id' => $this->submitter->discord_id,
            'format_id' => $format->id,
        ]);

        DiscordWebhookClient::fake(true);

        $job = new SendMapSubmissionWebhookJob($submission->id);
        $job->handle(app(DiscordWebhookClient::class));

        // Should not throw, webhook should not be sent
        $submission->refresh();
        $this->assertNull($submission->wh_msg_id);
    }

    public function test_job_builds_correct_payload_structure(): void
    {
        $format = Format::find(FormatConstants::MAPLIST);
        $format->map_submission_wh = 'https://discord.com/api/webhooks/test/webhook';
        $format->save();

        $submission = MapSubmission::factory()->create([
            'code' => $this->map->code,
            'submitter_id' => $this->submitter->discord_id,
            'format_id' => $format->id,
            'proposed' => 1,
            'subm_notes' => 'Test notes for submission',
        ]);

        DiscordWebhookClient::fake(true);

        $job = new SendMapSubmissionWebhookJob($submission->id);
        $job->handle(app(DiscordWebhookClient::class));

        $submission->refresh();
        $payload = json_decode($submission->wh_data, true);

        $expected = [
            'embeds' => [
                [
                    'title' => "{$this->map->name} - {$this->map->code}",
                    'url' => "https://join.btd6.com/Map/{$this->map->code}",
                    'color' => DiscordColors::PENDING,
                    'author' => ['name' => $this->submitter->name],
                    'description' => 'Test notes for submission',
                    'fields' => [[
                        'name' => 'Proposed Placement',
                        'value' => 'Top 3',
                        'inline' => true,
                    ]],
                ],
                [
                    'url' => "https://join.btd6.com/Map/{$this->map->code}",
                    'image' => ['url' => $this->map->map_preview_url],
                ],
            ],
        ];

        $this->assertEquals($expected, $payload);
    }

    public function test_job_handles_maplist_proposed_label(): void
    {
        $format = Format::find(FormatConstants::MAPLIST);
        $format->map_submission_wh = 'https://discord.com/api/webhooks/test/webhook';
        $format->save();

        $submission = MapSubmission::factory()->create([
            'code' => $this->map->code,
            'submitter_id' => $this->submitter->discord_id,
            'format_id' => $format->id,
            'proposed' => 1,
        ]);

        DiscordWebhookClient::fake(true);

        $job = new SendMapSubmissionWebhookJob($submission->id);
        $job->handle(app(DiscordWebhookClient::class));

        $submission->refresh();
        $payload = json_decode($submission->wh_data, true);

        $this->assertArrayHasKey('fields', $payload['embeds'][0]);
        $field = $payload['embeds'][0]['fields'][0];
        $this->assertEquals('Proposed Placement', $field['name']);
        $this->assertEquals('Top 3', $field['value']); // MAPLIST has proposed_difficulties set, so it looks up the label
    }

    public function test_job_handles_expert_list_proposed_label(): void
    {
        $format = Format::find(FormatConstants::EXPERT_LIST);
        $format->map_submission_wh = 'https://discord.com/api/webhooks/test/webhook';
        $format->proposed_difficulties = ['Beginner', 'Intermediate', 'Advanced'];
        $format->save();

        $submission = MapSubmission::factory()->create([
            'code' => $this->map->code,
            'submitter_id' => $this->submitter->discord_id,
            'format_id' => $format->id,
            'proposed' => 2, // Intermediate (1-indexed)
        ]);

        DiscordWebhookClient::fake(true);

        $job = new SendMapSubmissionWebhookJob($submission->id);
        $job->handle(app(DiscordWebhookClient::class));

        $submission->refresh();
        $payload = json_decode($submission->wh_data, true);

        $field = $payload['embeds'][0]['fields'][0];
        $this->assertEquals('Proposed Difficulty', $field['name']);
        $this->assertEquals('Intermediate', $field['value']);
    }

    public function test_job_handles_nostalgia_proposed_label(): void
    {
        $format = Format::find(FormatConstants::NOSTALGIA_PACK);
        $format->map_submission_wh = 'https://discord.com/api/webhooks/test/webhook';
        $format->save();

        $retroMap = RetroMap::factory()->create([
            'name' => 'Classic Retro Map',
        ]);

        $submission = MapSubmission::factory()->create([
            'code' => $this->map->code,
            'submitter_id' => $this->submitter->discord_id,
            'format_id' => $format->id,
            'proposed' => $retroMap->id,
        ]);

        DiscordWebhookClient::fake(true);

        $job = new SendMapSubmissionWebhookJob($submission->id);
        $job->handle(app(DiscordWebhookClient::class));

        $submission->refresh();
        $payload = json_decode($submission->wh_data, true);

        $field = $payload['embeds'][0]['fields'][0];
        $this->assertEquals('Proposed Remake', $field['name']);
        $this->assertEquals('Classic Retro Map', $field['value']);
    }

    public function test_job_handles_missing_notes(): void
    {
        $format = Format::find(FormatConstants::MAPLIST);
        $format->map_submission_wh = 'https://discord.com/api/webhooks/test/webhook';
        $format->save();

        $submission = MapSubmission::factory()->create([
            'code' => $this->map->code,
            'submitter_id' => $this->submitter->discord_id,
            'format_id' => $format->id,
            'proposed' => 1,
            'subm_notes' => null,
        ]);

        DiscordWebhookClient::fake(true);

        $job = new SendMapSubmissionWebhookJob($submission->id);
        $job->handle(app(DiscordWebhookClient::class));

        $submission->refresh();
        $payload = json_decode($submission->wh_data, true);

        // Description should not be set if notes are null
        $this->assertArrayNotHasKey('description', $payload['embeds'][0]);
    }

    public function test_job_handles_discord_error_gracefully(): void
    {
        $format = Format::find(FormatConstants::MAPLIST);
        $format->map_submission_wh = 'https://discord.com/api/webhooks/test/webhook';
        $format->save();

        $submission = MapSubmission::factory()->create([
            'code' => $this->map->code,
            'submitter_id' => $this->submitter->discord_id,
            'format_id' => $format->id,
        ]);

        DiscordWebhookClient::fake(false);

        $job = new SendMapSubmissionWebhookJob($submission->id);
        $job->handle(app(DiscordWebhookClient::class));

        $submission->refresh();
        $this->assertNull($submission->wh_msg_id);
        $this->assertNull($submission->wh_data);
    }

    public function test_job_fetches_nk_avatar_when_available(): void
    {
        $format = Format::find(FormatConstants::MAPLIST);
        $format->map_submission_wh = 'https://discord.com/api/webhooks/test/webhook';
        $format->save();

        $userWithOak = User::factory()
            ->withOak('test_oak_123')
            ->cachedFlair()
            ->create();

        $submission = MapSubmission::factory()->create([
            'code' => $this->map->code,
            'submitter_id' => $userWithOak->discord_id,
            'format_id' => $format->id,
            'proposed' => 1,
        ]);

        NinjaKiwiApiClient::fake([
            'avatar_url' => 'https://example.com/avatar.png',
            'banner_url' => null,
        ]);

        DiscordWebhookClient::fake(true);

        $job = new SendMapSubmissionWebhookJob($submission->id);
        $job->handle(app(DiscordWebhookClient::class));

        $submission->refresh();
        $payload = json_decode($submission->wh_data, true);

        $this->assertEquals('https://example.com/avatar.png', $payload['embeds'][0]['author']['icon_url']);
    }

    public function test_job_handles_missing_nk_oak(): void
    {
        $format = Format::find(FormatConstants::MAPLIST);
        $format->map_submission_wh = 'https://discord.com/api/webhooks/test/webhook';
        $format->save();

        $submission = MapSubmission::factory()->create([
            'code' => $this->map->code,
            'submitter_id' => $this->submitter->discord_id,
            'format_id' => $format->id,
            'proposed' => 1,
        ]);

        // Ensure user has no nk_oak
        $this->submitter->nk_oak = null;
        $this->submitter->save();

        DiscordWebhookClient::fake(true);

        $job = new SendMapSubmissionWebhookJob($submission->id);
        $job->handle(app(DiscordWebhookClient::class));

        $submission->refresh();
        $payload = json_decode($submission->wh_data, true);

        // icon_url should not be set
        $this->assertArrayNotHasKey('icon_url', $payload['embeds'][0]['author']);
    }
}
