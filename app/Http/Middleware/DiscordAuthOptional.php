<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DiscordAuthOptional
{
    /**
     * Handle an incoming request.
     *
     * Attempts to authenticate via Discord but allows the request to proceed
     * even if no valid token is provided.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Attempt to authenticate, but don't block if it fails
        auth()->guard('discord')->check();

        return $next($request);
    }
}
