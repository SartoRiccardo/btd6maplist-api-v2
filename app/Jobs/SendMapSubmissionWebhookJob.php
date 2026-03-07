<?php

namespace App\Jobs;

use App\Constants\DiscordColors;
use App\Constants\FormatConstants;
use App\Models\Map;
use App\Models\MapSubmission;
use App\Models\RetroMap;
use App\Services\Discord\DiscordWebhookClient;
use App\Services\NinjaKiwi\NinjaKiwiApiClient;
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

        // Fetch map data
        $map = Map::find($submission->code);

        if (!$map) {
            Log::warning('Map not found for submission webhook', [
                'map_code' => $submission->code,
                'submission_id' => $this->submissionId,
            ]);
            return;
        }

        // Fetch Ninja Kiwi avatar URL if available
        $avatarUrl = null;
        if ($submission->submitter->nk_oak) {
            $deco = NinjaKiwiApiClient::getBtd6UserDeco($submission->submitter->nk_oak);
            $avatarUrl = $deco['avatar_url'] ?? null;
        }

        // Build embeds
        $embeds = $this->buildEmbeds($submission, $map, $avatarUrl);

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
    private function buildEmbeds(MapSubmission $submission, Map $map, ?string $avatarUrl): array
    {
        $embed1 = $this->buildInfoEmbed($submission, $map, $avatarUrl);
        $embed2 = $this->buildImageEmbed($map);

        return [$embed1, $embed2];
    }

    /**
     * Build the primary info embed.
     */
    private function buildInfoEmbed(MapSubmission $submission, Map $map, ?string $avatarUrl): array
    {
        $embed = [
            'title' => "{$map->name} - {$submission->code}",
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

        return $embed;
    }

    /**
     * Build the image embed.
     */
    private function buildImageEmbed(Map $map): array
    {
        return [
            'url' => "https://join.btd6.com/Map/{$map->code}",
            'image' => [
                'url' => $map->map_preview_url,
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
            $difficultyIndex = $submission->proposed - 1; // proposed is 1-indexed
            $fieldValue = $proposedDifficulties[$difficultyIndex] ?? (string) $submission->proposed;
        }

        return [
            'name' => $fieldName,
            'value' => $fieldValue,
            'inline' => true,
        ];
    }
}
