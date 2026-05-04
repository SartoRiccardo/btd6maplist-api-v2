<?php

namespace App\Services\Discord;

use App\Constants\DiscordColors;
use App\Services\Discord\DiscordWebhookClient;
use Illuminate\Support\Facades\Log;

class DiscordEmbedService
{
    /**
     * Update the color of a Discord embed in an existing webhook message.
     *
     * @param string $webhookUrl The webhook URL
     * @param string $messageId The Discord message ID to update
     * @param array $payload The webhook payload containing embeds
     * @param bool $fail Whether to show failure (red) or success (green) status
     * @return bool True if successful, false otherwise
     */
    public function updateEmbedColor(
        string $webhookUrl,
        string $messageId,
        array $payload,
        bool $fail = false
    ): bool {
        // Validate payload has embeds
        if (!isset($payload['embeds'][0])) {
            Log::warning('Invalid webhook payload: no embeds found', [
                'message_id' => $messageId,
            ]);
            return false;
        }

        // Update the embed color
        $payload['embeds'][0]['color'] = $fail
            ? DiscordColors::FAIL
            : DiscordColors::ACCEPT;

        // Send update
        $webhookClient = app(DiscordWebhookClient::class);
        return $webhookClient->updateWebhookMessage(
            $webhookUrl,
            $messageId,
            $payload,
        );
    }
}
