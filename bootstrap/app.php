<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: []);
        $middleware->alias([
            'discord.auth' => \App\Http\Middleware\DiscordAuth::class,
            'discord.auth.optional' => \App\Http\Middleware\DiscordAuthOptional::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            '/web/oauth2/discord/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
