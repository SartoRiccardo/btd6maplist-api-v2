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
        // Both sides agree on a canonical JSON: sorted non-file fields + SHA-256 of each file,
        // grouped by field name alphabetically, preserving per-field submission order.
        $fields = $request->except(array_keys($request->allFiles()));
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
}
