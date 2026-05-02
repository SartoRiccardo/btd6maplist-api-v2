<?php

use Illuminate\Support\Facades\Route;

// Bot routes - NOT REGISTERED YET
// These routes use bot-specific authentication/middleware

Route::prefix('maps')->group(function () {
    Route::prefix('submit')->group(function () {
        /**
         * ❓ Unknown: Bot route
         */
        Route::post('/', fn() => response()->noContent(501));

        /**
         * ❓ Unknown: Bot route
         */
        Route::delete('/', fn() => response()->noContent(501));
    });

    Route::prefix('{code}/completions')->group(function () {
        /**
         * ❓ Unknown: Bot route
         */
        Route::post('/submit', fn() => response()->noContent(501));
    });
});

Route::prefix('formats')->group(function () {
    /**
     * ❓ Unknown: Bot route
     */
    Route::get('/', fn() => response()->noContent(501));
});

Route::prefix('roles')->group(function () {
    /**
     * ❓ Unknown: Bot route
     */
    Route::get('/achievement/updates', fn() => response()->noContent(501));

    /**
     * ❓ Unknown: Bot route
     */
    Route::post('/achievement/updates', fn() => response()->noContent(501));
});

Route::prefix('completions')->group(function () {
    /**
     * ❓ Unknown: Bot route
     */
    Route::put('{cid}/accept', fn() => response()->noContent(501));

    /**
     * ❓ Unknown: Bot route
     */
    Route::delete('{cid}', fn() => response()->noContent(501));
});

Route::prefix('users')->group(function () {
    /**
     * ❓ Unknown: Bot route
     */
    Route::get('{uid}', fn() => response()->noContent(501));

    /**
     * ❓ Unknown: Bot route
     */
    Route::put('{uid}', fn() => response()->noContent(501));
});

Route::put('/read-rules', [\App\Http\Controllers\UserController::class, 'readRules'])
    ->middleware(['bot.signature', 'bot.user']);
