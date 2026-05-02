<?php

use App\Http\Controllers\AchievementRoleController;
use App\Http\Controllers\CompletionController;
use App\Http\Controllers\MapSubmissionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('maps')->middleware(['bot.signature', 'bot.user'])
    ->controller(MapSubmissionController::class)
    ->group(function () {
        Route::prefix('submit')->group(function () {
            Route::post('/', 'store');
            Route::post('/reject', 'rejectByWebhook');
        });
    });

Route::prefix('roles')->middleware(['bot.signature'])
    ->controller(AchievementRoleController::class)
    ->group(function () {
        Route::get('/achievement/updates', 'linkedRoleUpdates');
        Route::post('/achievement/updates', 'refreshLinkedRoleSnapshot');
    });

Route::prefix('completions')->middleware(['bot.signature', 'bot.user'])
    ->controller(CompletionController::class)
    ->group(function () {
        Route::post('submit', 'submitByBot');
        Route::post('accept', 'accept');
        Route::post('reject', 'reject');
    });

Route::prefix('users')->middleware(['bot.signature', 'bot.user'])
    ->controller(UserController::class)
    ->group(function () {
        Route::put('{uid}', 'updateOak');
    });

Route::put('/read-rules', [UserController::class, 'readRules'])
    ->middleware(['bot.signature', 'bot.user']);
