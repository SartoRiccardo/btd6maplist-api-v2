<?php

use Illuminate\Support\Facades\Route;

Route::prefix('maps')->middleware(['bot.signature', 'bot.user'])->group(function () {
    Route::prefix('submit')->group(function () {
        Route::post('/', [\App\Http\Controllers\MapSubmissionController::class, 'store']);
        Route::post('/reject', [\App\Http\Controllers\MapSubmissionController::class, 'rejectByWebhook']);
    });
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

Route::prefix('completions')->middleware(['bot.signature', 'bot.user'])->group(function () {
    Route::post('submit', [\App\Http\Controllers\CompletionController::class, 'submitByBot']);
    Route::post('accept', [\App\Http\Controllers\CompletionController::class, 'accept']);
    Route::post('reject', [\App\Http\Controllers\CompletionController::class, 'reject']);
});

Route::prefix('users')->middleware(['bot.signature', 'bot.user'])->group(function () {
    Route::put('{uid}', [\App\Http\Controllers\UserController::class, 'updateOak']);
});

Route::put('/read-rules', [\App\Http\Controllers\UserController::class, 'readRules'])
    ->middleware(['bot.signature', 'bot.user']);
