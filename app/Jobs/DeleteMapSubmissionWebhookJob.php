<?php

namespace App\Jobs;

use App\Services\Discord\DiscordWebhookClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DeleteMapSubmissionWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $webhookUrl;
    public string $messageId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $webhookUrl, string $messageId)
    {
        $this->webhookUrl = $webhookUrl;
        $this->messageId = $messageId;
    }

    /**
     * Execute the job.
     */
    public function handle(DiscordWebhookClient $webhookClient): void
    {
        $success = $webhookClient->deleteWebhookMessage($this->webhookUrl, $this->messageId);

        if (!$success) {
            Log::warning('Failed to delete map submission webhook message', [
                'webhook_url' => $this->webhookUrl,
                'message_id' => $this->messageId,
            ]);
        }
    }
}
