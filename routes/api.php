<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\FormatController;
use App\Http\Controllers\RetroMapController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\CompletionController;
use App\Http\Controllers\DiscordUtilitiesController;
use App\Http\Controllers\PlatformRoleController;
use App\Http\Controllers\AchievementRoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ImageGeneratorController;

Route::put('/read-rules', [UserController::class, 'readRules'])
    ->middleware('discord.auth');

// Config endpoints
Route::prefix('config')
    ->controller(ConfigController::class)
    ->group(function () {
        Route::get('/', 'index');
        Route::put('/', 'update')
            ->middleware('discord.auth');
    });

// Format endpoints
Route::prefix('formats')
    ->controller(FormatController::class)
    ->group(function () {
        Route::get('/', 'index');
        Route::get('/{id}', 'show');
        Route::put('/{id}', 'update')
            ->middleware('discord.auth');
        Route::get('/{id}/leaderboard', 'leaderboard');
    });

// Retro Map endpoints
Route::prefix('maps/retro')
    ->controller(RetroMapController::class)
    ->group(function () {
        Route::get('/', 'index');
        Route::get('/{id}', 'show');

        Route::middleware('discord.auth')
            ->group(function () {
                Route::post('/', 'save');
                Route::put('/{id}', 'update');
                Route::delete('/{id}', 'destroy');
            });
    });

// Map endpoints
Route::prefix('maps')
    ->controller(MapController::class)
    ->group(function () {
        Route::get('/', 'index');
        Route::get('/{id}', 'show');

        Route::middleware('discord.auth')
            ->group(function () {
                Route::post('/submissions', 'submit');

                Route::post('/', 'save');
                Route::put('/{id}', 'update');
                Route::delete('/{id}', 'destroy');
                Route::put('/{code}/completions/transfer', 'transferCompletions');
            });
    });

// Completion endpoints
Route::prefix('completions')
    ->controller(CompletionController::class)
    ->group(function () {
        Route::get('/', 'index');
        Route::get('/{id}', 'show');

        Route::middleware('discord.auth')
            ->group(function () {
                Route::post('/', 'save');
                Route::post('/submit', 'submit');
                Route::put('/{id}', 'update');
                Route::delete('/{id}', 'destroy');
            });
    });

Route::post('/server-roles', [DiscordUtilitiesController::class, 'serverRoles']);

// Platform Roles endpoints
Route::prefix('roles/platform')
    ->controller(PlatformRoleController::class)
    ->group(function () {
        Route::get('/', 'index');
    });

// Achievement Roles endpoints
Route::prefix('roles/achievement')
    ->controller(AchievementRoleController::class)
    ->group(function () {
        Route::get('/', 'index');

        Route::middleware('discord.auth')
            ->group(function () {
                Route::put('/', 'update');
            });
    });

// Users endpoints
Route::prefix('users')
    ->controller(UserController::class)
    ->group(function () {
        Route::get('/{id}', 'show');

        Route::middleware('discord.auth')
            ->group(function () {
                Route::put('/{id}', 'update');
                Route::put('/{id}/ban', 'banUser');
                Route::put('/{id}/unban', 'unbanUser');
            });
    });

Route::get('/search', [SearchController::class, 'search']);

Route::get('/img/medal-banner/{banner}', [ImageGeneratorController::class, 'medalBanner']);
