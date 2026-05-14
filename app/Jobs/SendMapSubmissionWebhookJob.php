<?php

namespace App\Jobs;

use App\Constants\DiscordColors;
use App\Constants\Queues;
use App\Constants\FormatConstants;
use App\Models\MapSubmission;
use App\Models\RetroMap;
use App\Services\NinjaKiwi\NinjaKiwiApiClient;
use App\Services\Discord\DiscordWebhookClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendMapSubmissionWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $submissionId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $submissionId)
    {
        $this->submissionId = $submissionId;
        $this->onQueue(Queues::DISCORD);
    }

    /**
     * Execute the job.
     */
    public function handle(DiscordWebhookClient $webhookClient): void
    {
        $submission = MapSubmission::with(['submitter', 'format'])->find($this->submissionId);

        if (!$submission) {
            Log::warning('Map submission not found for webhook', [
                'submission_id' => $this->submissionId,
            ]);
            return;
        }

        $webhookUrl = $submission->format->map_submission_wh;

        if (!$webhookUrl) {
            return; // No webhook configured for this format
        }

        // Fetch Ninja Kiwi avatar URL from cache if available
        $avatarUrl = null;
        if ($submission->submitter->nk_oak) {
            $avatarUrl = $submission->submitter->avatar_url;
        }

        $mapName = NinjaKiwiApiClient::getMapName($submission->code) ?? $submission->code;

        // Build embeds
        $embeds = $this->buildEmbeds($submission, $mapName, $avatarUrl);

        $payload = [
            'embeds' => $embeds,
        ];

        // Send webhook
        $messageId = $webhookClient->sendMapSubmissionWebhook($webhookUrl, $payload);

        if ($messageId) {
            // Save message ID and payload
            $submission->wh_msg_id = $messageId;
            $submission->wh_data = json_encode($payload);
            $submission->save();
        } else {
            Log::error('Failed to send map submission webhook', [
                'submission_id' => $this->submissionId,
            ]);
        }
    }

    /**
     * Build dual embed structure for map submission.
     *
     * @return array<int, array>
     */
    private function buildEmbeds(MapSubmission $submission, string $mapName, ?string $avatarUrl): array
    {
        $embed1 = $this->buildInfoEmbed($submission, $mapName, $avatarUrl);
        $embed2 = $this->buildImageEmbed($submission->code);

        return [$embed1, $embed2];
    }

    /**
     * Build the primary info embed.
     */
    private function buildInfoEmbed(MapSubmission $submission, string $mapName, ?string $avatarUrl): array
    {
        $embed = [
            'title' => "{$mapName} - {$submission->code}",
            'url' => "https://join.btd6.com/Map/{$submission->code}",
            'color' => DiscordColors::PENDING,
            'author' => [
                'name' => $submission->submitter->name,
            ],
            'fields' => [
                $this->getFormatSpecificField($submission),
            ],
        ];

        // Add avatar URL if available
        if ($avatarUrl) {
            $embed['author']['icon_url'] = $avatarUrl;
        }

        // Add description if notes exist
        if ($submission->subm_notes) {
            $embed['description'] = $submission->subm_notes;
        }

        // Add proof image
        if ($submission->completion_proof) {
            $embed['image'] = ['url' => $submission->completion_proof];
        }

        // Add video proof URLs if available
        $videoUrls = $submission->video_proof_urls ?? [];
        if (!empty($videoUrls)) {
            $fieldName = count($videoUrls) === 1 ? 'Video Proof URL' : 'Video Proof URLs';
            $fieldValue = count($videoUrls) === 1
                ? $videoUrls[0]
                : '- ' . implode("\n- ", $videoUrls);

            $embed['fields'][] = [
                'name' => $fieldName,
                'value' => $fieldValue,
                'inline' => false,
            ];
        }

        return $embed;
    }

    /**
     * Build the image embed.
     */
    private function buildImageEmbed(string $code): array
    {
        return [
            'url' => "https://join.btd6.com/Map/{$code}",
            'image' => [
                'url' => "https://nkproxy.sarto.dev/map/{$code}.jpg",
            ],
        ];
    }

    /**
     * Get format-specific field for the embed.
     *
     * @return array|null Field array or null if no field needed
     */
    private function getFormatSpecificField(MapSubmission $submission): ?array
    {
        $fieldName = match ($submission->format_id) {
            FormatConstants::MAPLIST, FormatConstants::MAPLIST_ALL_VERSIONS => 'Proposed Placement',
            FormatConstants::NOSTALGIA_PACK => 'Proposed Remake',
            default => 'Proposed Difficulty',
        };

        // Nostalgia Pack: Query RetroMap
        if ($submission->format_id === FormatConstants::NOSTALGIA_PACK) {
            $retroMap = RetroMap::find($submission->proposed);
            $fieldValue = $retroMap?->name ?? 'Unknown Retro Map';
        } else {
            // Default: Look up label from proposed_difficulties array, fallback to raw value
            $proposedDifficulties = $submission->format->proposed_difficulties ?? [];
            $fieldValue = $proposedDifficulties[$submission->proposed] ?? (string) $submission->proposed;
        }

        return [
            'name' => $fieldName,
            'value' => $fieldValue,
            'inline' => true,
        ];
    }
}
