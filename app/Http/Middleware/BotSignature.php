<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BotSignature
{
    private const MAX_AGE_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $timestamp = $request->header('X-Timestamp');
        $signature = $request->header('X-Signature');

        if (!$timestamp || !$signature) {
            return response()->json(['error' => 'Missing signature headers'], 401);
        }

        if (abs(time() - (int) $timestamp) > self::MAX_AGE_SECONDS) {
            return response()->json(['error' => 'Request timestamp expired'], 401);
        }

        $secret = config('app.bot_secret');
        $body = $this->signingBody($request);
        $method = strtoupper($request->method());
        $path = $request->getPathInfo();

        $expected = hash_hmac('sha256', "{$timestamp}\n{$method}\n{$path}\n{$body}", $secret);

        if (!hash_equals($expected, $signature)) {
            \Log::debug('BotSignature mismatch', [
                'method'    => $method,
                'path'      => $path,
                'timestamp' => $timestamp,
                'expected'  => $expected,
                'received'  => $signature,
                'body'      => $body,
            ]);
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        return $next($request);
    }

    private function signingBody(Request $request): string
    {
        if (!str_contains($request->header('Content-Type', ''), 'multipart/form-data')) {
            return $request->getContent();
        }

        // php://input is unavailable for multipart/form-data (PHP consumes it at the SAPI level).
        // Both sides agree on a canonical JSON: field names kept as PHP bracket notation
        // (e.g. _user[discord_id]), sorted alphabetically + SHA-256 of each file.
        $fields = $this->flattenFields($request->except(array_keys($request->allFiles())));
        ksort($fields);

        $allFiles = $request->allFiles();
        ksort($allFiles);
        $fileHashes = [];
        foreach ($allFiles as $files) {
            foreach (is_array($files) ? $files : [$files] as $file) {
                $fileHashes[] = hash_file('sha256', $file->getRealPath());
            }
        }

        return json_encode(
            ['fields' => $fields, 'file_hashes' => $fileHashes],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    private function flattenFields(array $arr, string $prefix = ''): array
    {
        $result = [];
        foreach ($arr as $key => $value) {
            $fullKey = $prefix !== '' ? "{$prefix}[{$key}]" : (string) $key;
            if (is_array($value)) {
                $result += $this->flattenFields($value, $fullKey);
            } else {
                $result[$fullKey] = $value;
            }
        }
        return $result;
    }
}
