<?php

namespace App\Providers;

use App\Auth\DiscordGuard;
use App\Listeners\DiscordSocialiteEventListener;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::extend('discord', function ($app, $name, array $config) {
            return new DiscordGuard(
                Auth::createUserProvider($config['provider'] ?? null),
                $app->make('request')
            );
        });

        // Register Discord Socialite provider
        Event::listen(
            SocialiteWasCalled::class,
            [DiscordSocialiteEventListener::class, 'handle']
        );
    }
}
