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

        if ($response->getStatusCode() === 422) {
            \Log::debug('422 Unprocessable', [
                'method' => $request->method(),
                'path'   => $request->getPathInfo(),
                'input'  => $request->except(['password', 'password_confirmation']),
                'body'   => $response->getContent(),
            ]);
        }

        return $response;
    }
}
