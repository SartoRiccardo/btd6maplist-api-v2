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
        $body = $request->getContent();
        $method = strtoupper($request->method());
        $path = $request->getPathInfo();

        $expected = hash_hmac('sha256', "{$timestamp}\n{$method}\n{$path}\n{$body}", $secret);

        if (!hash_equals($expected, $signature)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        return $next($request);
    }
}
