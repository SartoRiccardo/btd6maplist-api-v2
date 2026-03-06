<?php

namespace App\Services\NinjaKiwi;

class NinjaKiwiApiClient
{
    protected static ?array $fakeDeco = null;
    protected static ?array $fakeMapExists = null;

    /**
     * Get BTD6 user decoration (avatar and banner URLs) from Ninja Kiwi API.
     *
     * @param string $oak The user's OAK (OpenAPI Key)
     * @return array Array with 'avatarURL' and 'bannerURL' keys (null if not found)
     */
    public static function getBtd6UserDeco(string $oak): array
    {
        if (self::$fakeDeco !== null) {
            return self::$fakeDeco;
        }

        $response = \Http::get("https://data.ninjakiwi.com/btd6/users/{$oak}");

        if ($response->failed() || !$response->json('success')) {
            return ['avatar_url' => null, 'banner_url' => null];
        }

        $body = $response->json('body', []);

        return [
            'avatar_url' => $body['avatarURL'] ?? null,
            'banner_url' => $body['bannerURL'] ?? null,
        ];
    }

    /**
     * Check if a BTD6 map exists by its code.
     *
     * @param string $code The map code
     * @return bool True if the map exists, false otherwise
     */
    public static function mapExists(string $code): bool
    {
        if (self::$fakeMapExists !== null) {
            return self::$fakeMapExists[$code] ?? false;
        }

        $response = \Http::get("https://data.ninjakiwi.com/btd6/maps/map/{$code}");

        if ($response->failed()) {
            return false;
        }

        return $response->json('success') === true;
    }

    /**
     * Fake the Ninja Kiwi API for testing.
     *
     * @param array $deco Array with 'avatarURL' and 'bannerURL' keys
     */
    public static function fake(array $deco): void
    {
        self::$fakeDeco = $deco;
    }

    /**
     * Fake map existence for testing.
     *
     * @param array $mapExists Array of map codes with boolean values
     */
    public static function fakeMapExists(array $mapExists): void
    {
        self::$fakeMapExists = $mapExists;
    }

    /**
     * Clear fake data.
     */
    public static function clearFake(): void
    {
        self::$fakeDeco = null;
        self::$fakeMapExists = null;
    }
}
