<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('api')
                ->prefix('bot')
                ->group(base_path('routes/bot.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\Log422Responses::class);
        $middleware->api(prepend: [
            \App\Http\Middleware\ForceJsonResponse::class,
        ]);
        $middleware->alias([
            'discord.auth' => \App\Http\Middleware\DiscordAuth::class,
            'discord.auth.optional' => \App\Http\Middleware\DiscordAuthOptional::class,
            'bot.signature' => \App\Http\Middleware\BotSignature::class,
            'bot.user' => \App\Http\Middleware\BotUserResolver::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            '/web/oauth2/discord/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
