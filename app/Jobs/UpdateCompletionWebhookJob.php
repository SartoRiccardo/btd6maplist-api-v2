<?php

namespace App\Jobs;

use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\Format;
use App\Services\Discord\DiscordEmbedService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateCompletionWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $completionId;
    public bool $fail;

    /**
     * Create a new job instance.
     */
    public function __construct(int $completionId, bool $fail = false)
    {
        $this->completionId = $completionId;
        $this->fail = $fail;
    }

    /**
     * Execute the job.
     */
    public function handle(DiscordEmbedService $embedService): void
    {
        $completion = Completion::find($this->completionId);

        if (!$completion || !$completion->wh_msg_id || !$completion->wh_data) {
            return; // No webhook data to update
        }

        // Get the active meta to determine format
        $meta = CompletionMeta::activeForCompletion($completion->id, Carbon::now());

        if (!$meta) {
            return;
        }

        $format = Format::find($meta->format_id);
        if (!$format || !$format->run_submission_wh) {
            return; // No webhook configured
        }

        // Parse stored payload
        $payload = json_decode($completion->wh_data, true);
        if (!$payload) {
            Log::warning('Invalid webhook payload JSON', [
                'completion_id' => $this->completionId,
            ]);
            return;
        }

        // Update webhook via service
        $success = $embedService->updateEmbedColor(
            $format->run_submission_wh,
            $completion->wh_msg_id,
            $payload,
            fail: $this->fail
        );

        if (!$success) {
            Log::error('Failed to update completion webhook', [
                'completion_id' => $this->completionId,
            ]);
        }
    }
}
