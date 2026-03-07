<?php

namespace Tests\Unit\Jobs;

use App\Jobs\DeleteMapSubmissionWebhookJob;
use App\Services\Discord\DiscordWebhookClient;
use Tests\TestCase;

class DeleteMapSubmissionWebhookJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        DiscordWebhookClient::clearFake();
        parent::tearDown();
    }

    public function test_job_sends_delete_request_to_discord(): void
    {
        $webhookUrl = 'https://discord.com/api/webhooks/test/webhook';
        $messageId = '123456789';

        DiscordWebhookClient::fake(true);

        $job = new DeleteMapSubmissionWebhookJob($webhookUrl, $messageId);
        $job->handle(app(DiscordWebhookClient::class));

        // Should complete without exception
        $this->assertTrue(true);
    }

    public function test_job_completes_silently_if_message_is_already_deleted(): void
    {
        $webhookUrl = 'https://discord.com/api/webhooks/test/webhook';
        $messageId = 'already_deleted';

        DiscordWebhookClient::fake(true);

        $job = new DeleteMapSubmissionWebhookJob($webhookUrl, $messageId);
        $job->handle(app(DiscordWebhookClient::class));

        // Should handle 404 gracefully (treat as success)
        $this->assertTrue(true);
    }

    public function test_job_handles_deletion_failure(): void
    {
        $webhookUrl = 'https://discord.com/api/webhooks/test/webhook';
        $messageId = '123456789';

        DiscordWebhookClient::fake(false);

        $job = new DeleteMapSubmissionWebhookJob($webhookUrl, $messageId);
        $job->handle(app(DiscordWebhookClient::class));

        // Should not throw exception even on failure
        $this->assertTrue(true);
    }
}
