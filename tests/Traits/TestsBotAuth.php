<?php

namespace Tests\Traits;

trait TestsBotAuth
{
    protected const BOT_SECRET = 'test_bot_secret';
    protected const BOT_USER_ID = '9000000';
    protected const BOT_USERNAME = 'bot_test_user';

    abstract protected function endpoint(): string;
    abstract protected function method(): string;

    protected function botRequestData(): array
    {
        return [];
    }

    protected function makeBotRequest(array $headers = [], ?array $body = null): \Illuminate\Testing\TestResponse
    {
        $data = $body ?? array_merge($this->botRequestData(), [
            '_user' => [
                'discord_id' => self::BOT_USER_ID,
                'name' => self::BOT_USERNAME,
            ],
        ]);

        $request = $this;
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return match (strtoupper($this->method())) {
            'GET'    => $request->getJson($this->endpoint()),
            'POST'   => $request->postJson($this->endpoint(), $data),
            'PUT'    => $request->putJson($this->endpoint(), $data),
            'DELETE' => $request->deleteJson($this->endpoint(), $data),
        };
    }

    protected function makeBotSignedRequest(?array $body = null, ?int $timestamp = null): \Illuminate\Testing\TestResponse
    {
        config(['app.bot_secret' => self::BOT_SECRET]);

        $data = $body ?? array_merge($this->botRequestData(), [
            '_user' => [
                'discord_id' => self::BOT_USER_ID,
                'name' => self::BOT_USERNAME,
            ],
        ]);

        $timestamp ??= time();
        $method = strtoupper($this->method());
        $path = parse_url($this->endpoint(), PHP_URL_PATH);
        $bodyStr = json_encode($data);

        $signature = hash_hmac('sha256', "{$timestamp}\n{$method}\n{$path}\n{$bodyStr}", self::BOT_SECRET);

        return $this->makeBotRequest([
            'X-Timestamp' => $timestamp,
            'X-Signature' => $signature,
        ], $data);
    }

    // -- Injected tests -- //

    public function test_bot_no_signature_headers_returns_401(): void
    {
        $this->makeBotRequest()
            ->assertStatus(401)
            ->assertJson(['error' => 'Missing signature headers']);
    }

    public function test_bot_expired_timestamp_returns_401(): void
    {
        config(['app.bot_secret' => self::BOT_SECRET]);

        $expiredTimestamp = time() - 301;
        $method = strtoupper($this->method());
        $path = parse_url($this->endpoint(), PHP_URL_PATH);
        $data = array_merge($this->botRequestData(), [
            '_user' => ['discord_id' => self::BOT_USER_ID, 'name' => self::BOT_USERNAME],
        ]);
        $bodyStr = json_encode($data);
        $signature = hash_hmac('sha256', "{$expiredTimestamp}\n{$method}\n{$path}\n{$bodyStr}", self::BOT_SECRET);

        $this->makeBotRequest([
            'X-Timestamp' => $expiredTimestamp,
            'X-Signature' => $signature,
        ], $data)
            ->assertStatus(401)
            ->assertJson(['error' => 'Request timestamp expired']);
    }

    public function test_bot_mismatched_signature_returns_401(): void
    {
        $this->makeBotRequest([
            'X-Timestamp' => time(),
            'X-Signature' => 'deadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeef',
        ])
            ->assertStatus(401)
            ->assertJson(['error' => 'Invalid signature']);
    }
}
