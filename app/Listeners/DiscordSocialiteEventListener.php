<?php

namespace App\Listeners;

use SocialiteProviders\Manager\SocialiteWasCalled;

class DiscordSocialiteEventListener
{
    /**
     * Register the Discord Socialite provider.
     */
    public function handle(SocialiteWasCalled $event): void
    {
        $event->extendSocialite('discord', \SocialiteProviders\Discord\Provider::class);
    }
}
