<?php

namespace App\Services\Discord;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordWebhookClient
{
    protected static ?bool $fakeResult = null;
    protected static ?string $fakeMessageId = null;

    /**
     * Update a Discord webhook message.
     *
     * @param string $webhookUrl The webhook URL
     * @param string $messageId The message ID to update
     * @param array $payload The payload with embeds (color should already be set)
     * @return bool True if successful, false otherwise
     */
    public function updateWebhookMessage(string $webhookUrl, string $messageId, array $payload): bool
    {
        if (self::$fakeResult !== null) {
            return self::$fakeResult;
        }

        $response = Http::patch("{$webhookUrl}/messages/{$messageId}", $payload);

        return $response->successful();
    }

    /**
     * Send a new Discord webhook message for a map submission.
     *
     * @param string $webhookUrl The webhook URL
     * @param array $payload The webhook payload with embeds
     * @return string|null The message ID if successful, null otherwise
     */
    public function sendMapSubmissionWebhook(string $webhookUrl, array $payload): ?string
    {
        if (self::$fakeResult !== null) {
            return self::$fakeResult ? (self::$fakeMessageId ?? '123456789') : null;
        }

        $response = Http::post("{$webhookUrl}?wait=true", $payload);

        if ($response->successful()) {
            return $response->json('id');
        }

        Log::warning('Discord webhook send failed', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return null;
    }

    /**
     * Delete a Discord webhook message.
     *
     * @param string $webhookUrl The webhook URL
     * @param string $messageId The message ID to delete
     * @return bool True if successful or already deleted (404), false otherwise
     */
    public function deleteWebhookMessage(string $webhookUrl, string $messageId): bool
    {
        if (self::$fakeResult !== null) {
            return self::$fakeResult;
        }

        $response = Http::delete("{$webhookUrl}/messages/{$messageId}");

        // 404 means message already deleted - treat as success
        $success = $response->successful() || $response->status() === 404;

        if (!$success) {
            Log::warning('Discord webhook delete failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        return $success;
    }

    /**
     * Fake the webhook client for testing.
     *
     * @param bool $result The result to return from updateWebhookMessage
     * @param string|null $fakeMessageId Optional fake message ID to return from sendMapSubmissionWebhook
     */
    public static function fake(bool $result = true, ?string $fakeMessageId = null): void
    {
        self::$fakeResult = $result;
        self::$fakeMessageId = $fakeMessageId;
    }

    /**
     * Clear the fake result.
     */
    public static function clearFake(): void
    {
        self::$fakeResult = null;
        self::$fakeMessageId = null;
    }
}
