<?php

namespace App\Jobs;

use App\Constants\DiscordColors;
use App\Constants\Queues;
use App\Constants\ProofType;
use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Services\Discord\DiscordWebhookClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendCompletionSubmissionWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $completionId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $completionId)
    {
        $this->completionId = $completionId;
        $this->onQueue(Queues::DISCORD);
    }

    /**
     * Execute the job.
     */
    public function handle(DiscordWebhookClient $webhookClient): void
    {
        $completion = Completion::with(['map', 'proofs'])
            ->find($this->completionId);

        if (!$completion) {
            Log::warning('Completion not found for webhook', [
                'completion_id' => $this->completionId,
            ]);
            return;
        }

        // Get the most recent meta (should be the one just created)
        $meta = CompletionMeta::with(['players', 'format'])
            ->where('completion_id', $completion->id)
            ->orderBy('created_on', 'desc')
            ->first();

        if (!$meta || !$meta->format) {
            Log::warning('Completion meta or format not found', [
                'completion_id' => $this->completionId,
            ]);
            return;
        }

        $webhookUrl = $meta->format->run_submission_wh;

        if (!$webhookUrl) {
            return; // No webhook configured for this format
        }

        // Build embeds
        $embeds = $this->buildEmbeds($completion, $meta);

        $payload = ['embeds' => $embeds];

        // Send webhook
        $messageId = $webhookClient->sendMapSubmissionWebhook($webhookUrl, $payload);

        if ($messageId) {
            $completion->wh_msg_id = $messageId;
            $completion->wh_data = json_encode($payload);
            $completion->save();
        } else {
            Log::error('Failed to send completion submission webhook', [
                'completion_id' => $this->completionId,
            ]);
        }
    }

    /**
     * Build multi-embed structure for completion submission.
     *
     * @return array<int, array>
     */
    private function buildEmbeds(Completion $completion, CompletionMeta $meta): array
    {
        $embeds = [];

        // Build info embed (always first)
        $embeds[] = $this->buildInfoEmbed($completion, $meta);

        // Get all image proofs (skip the first one, already shown in info embed)
        $imageProofs = $completion->proofs
            ->where('proof_type', ProofType::IMAGE)
            ->pluck('proof_url')
            ->values()
            ->skip(1) // Skip first image (already in info embed)
            ->take(3) // Max 3 additional images
            ->toArray();

        // Build additional image embeds (up to 3 more)
        foreach ($imageProofs as $imageUrl) {
            $embeds[] = $this->buildImageEmbed($completion->map->code, $imageUrl);
        }

        return $embeds;
    }

    /**
     * Build the primary info embed.
     */
    private function buildInfoEmbed(Completion $completion, CompletionMeta $meta): array
    {
        $map = $completion->map;
        $format = $meta->format;

        $formatLabel = $format->emoji
            ? "{$format->emoji} {$format->name}"
            : $format->name;

        // Build flag emojis — only shown when at least one is set
        $flagEmojis = [];
        if ($meta->lcc_id && $meta->lcc) {
            $flagEmojis[] = '<:m_lcc:1285726686117236828>';
        }
        if ($meta->black_border) {
            $flagEmojis[] = '<:m_bb:1285726683709702205>';
        }
        if ($meta->no_geraldo) {
            $flagEmojis[] = '<:m_noopthero:1285726689845968936>';
        }

        // Get LCC leftover — only shown when present
        $lccText = null;
        if ($meta->lcc_id && $meta->lcc) {
            $lccData = $meta->lcc;
            if (is_array($lccData) && isset($lccData['leftover'])) {
                $lccText = '$' . number_format((int) $lccData['leftover'], 0, ',', '.');
            }
        }

        // Get video proof URLs
        $videoProofs = $completion->proofs
            ->where('proof_type', ProofType::VIDEO)
            ->pluck('proof_url')
            ->values()
            ->toArray();

        // Get first image proof
        $firstImage = $completion->proofs
            ->where('proof_type', ProofType::IMAGE)
            ->first()?->proof_url;

        // Get submitter (first player) for avatar
        $submitter = $meta->players->first();
        $avatarUrl = null;
        if ($submitter && $submitter->nk_oak) {
            $avatarUrl = $submitter->avatar_url;
        }

        $frontendUrl = env('FRONTEND_URL');

        $fields = [
            [
                'name' => 'Format',
                'value' => $formatLabel,
                'inline' => true,
            ],
        ];

        if (!empty($flagEmojis)) {
            $fields[] = [
                'name' => 'Flags',
                'value' => implode(' ', $flagEmojis),
                'inline' => true,
            ];
        }

        if ($lccText !== null) {
            $fields[] = [
                'name' => 'LCC Leftover',
                'value' => $lccText,
                'inline' => true,
            ];
        }

        $embed = [
            'title' => $map->name,
            'url' => $frontendUrl ? "{$frontendUrl}/map/{$map->code}" : null,
            'color' => DiscordColors::PENDING,
            'fields' => $fields,
        ];

        // Add author with avatar if available
        if ($submitter) {
            $embed['author'] = [
                'name' => $submitter->name,
            ];
            if ($avatarUrl) {
                $embed['author']['icon_url'] = $avatarUrl;
            }
        }

        // Add description if notes exist
        if ($completion->subm_notes) {
            $embed['description'] = $completion->subm_notes;
        }

        // Add image if available
        if ($firstImage) {
            $embed['image'] = [
                'url' => $firstImage,
            ];
        }

        // Add video proof URLs if available
        if (!empty($videoProofs)) {
            $fieldName = count($videoProofs) === 1
                ? 'Video Proof URL'
                : 'Video Proof URLs';

            $fieldValue = count($videoProofs) === 1
                ? $videoProofs[0]
                : '- ' . implode("\n- ", $videoProofs);

            $embed['fields'][] = [
                'name' => $fieldName,
                'value' => $fieldValue,
                'inline' => false,
            ];
        }

        return $embed;
    }

    /**
     * Build an image embed.
     */
    private function buildImageEmbed(string $mapCode, string $imageUrl): array
    {
        $frontendUrl = env('FRONTEND_URL');

        return [
            'url' => $frontendUrl ? "{$frontendUrl}/maps/{$mapCode}" : null,
            'image' => [
                'url' => $imageUrl,
            ],
        ];
    }
}
