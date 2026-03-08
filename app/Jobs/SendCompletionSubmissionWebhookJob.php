<?php

namespace App\Jobs;

use App\Constants\DiscordColors;
use App\Constants\ProofType;
use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\Format;
use App\Models\LeastCostChimps;
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

        // Build embed
        $embed = $this->buildEmbed($completion, $meta);

        $payload = ['embeds' => [$embed]];

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
     * Build the completion submission embed.
     */
    private function buildEmbed(Completion $completion, CompletionMeta $meta): array
    {
        $map = $completion->map;

        // Get player names
        $playerNames = $meta->players->pluck('name')->implode(', ');

        // Build flags
        $flags = [];
        if ($meta->black_border) {
            $flags[] = 'BB';
        }
        if ($meta->no_geraldo) {
            $flags[] = 'NoG';
        }
        $flagsStr = empty($flags) ? 'None' : implode(', ', $flags);

        // Get LCC leftover
        $lccText = 'N/A';
        if ($meta->lcc_id && $meta->lcc) {
            // lcc accessor returns an array, so we can access it directly
            $lccData = $meta->lcc;
            if (is_array($lccData) && isset($lccData['leftover'])) {
                $lccText = "\${$lccData['leftover']}";
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

        $embed = [
            'title' => "Run Submission: {$map->name} - {$map->code}",
            'color' => DiscordColors::PENDING,
            'fields' => [
                [
                    'name' => 'Format',
                    'value' => $meta->format->name,
                    'inline' => true,
                ],
                [
                    'name' => 'Players',
                    'value' => $playerNames ?: 'Unknown',
                    'inline' => true,
                ],
                [
                    'name' => 'Flags',
                    'value' => $flagsStr,
                    'inline' => true,
                ],
                [
                    'name' => 'LCC Leftover',
                    'value' => $lccText,
                    'inline' => true,
                ],
            ],
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
}
