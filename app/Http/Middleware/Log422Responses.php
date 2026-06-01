<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Log422Responses
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $status = $response->getStatusCode();
        if ($status < 400) {
            return $response;
        }

        $user = auth()->guard('discord')->user();
        $context = [
            'method' => $request->method(),
            'path' => $request->getPathInfo(),
            'status' => $status,
            'discord_id' => $user?->discord_id,
        ];

        $contentType = $response->headers->get('Content-Type', '');
        if (str_contains($contentType, 'application/json')) {
            $context['body'] = json_decode($response->getContent(), true);
        }

        \Log::warning('http.error', $context);

        return $response;
    }
}
