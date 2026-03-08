<?php

namespace App\Jobs;

use App\Models\MapSubmission;
use App\Services\Discord\DiscordEmbedService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateMapSubmissionWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $submissionId;
    public bool $fail;

    /**
     * Create a new job instance.
     */
    public function __construct(int $submissionId, bool $fail = false)
    {
        $this->submissionId = $submissionId;
        $this->fail = $fail;
    }

    /**
     * Execute the job.
     */
    public function handle(DiscordEmbedService $embedService): void
    {
        $submission = MapSubmission::with('format')->find($this->submissionId);

        if (!$submission || !$submission->wh_msg_id || !$submission->wh_data) {
            return; // No webhook data to update
        }

        $format = $submission->format;
        if (!$format || !$format->map_submission_wh) {
            return; // No webhook configured
        }

        // Parse stored payload
        $payload = json_decode($submission->wh_data, true);
        if (!$payload) {
            Log::warning('Invalid webhook payload JSON', [
                'submission_id' => $this->submissionId,
            ]);
            return;
        }

        // Update webhook via service
        $success = $embedService->updateEmbedColor(
            $format->map_submission_wh,
            $submission->wh_msg_id,
            $payload,
            fail: $this->fail
        );

        if (!$success) {
            Log::error('Failed to update map submission webhook', [
                'submission_id' => $this->submissionId,
            ]);
        }
    }
}
