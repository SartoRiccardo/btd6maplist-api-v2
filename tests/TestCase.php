<?php

namespace Tests;

use App\Models\Role;
use App\Models\RoleFormatPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;


/**
 * Base test case for all feature tests that require database transactions.
 *
 * This class provides:
 * - Automatic transaction wrapping for each test
 * - Default database seeding via DatabaseSeeder
 * - Consistent test isolation
 *
 * All feature test base classes should extend this instead of TestCase directly.
 */

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected static $migrated = false;

    protected function beforeRefreshingDatabase()
    {
        if (!self::$migrated) {
            if ($this->needsDatabaseRefresh()) {
                \Log::info('Starting database refresh for test: ' . static::class);
                Artisan::call('migrate:fresh');
                \Log::info('Seeding database for test: ' . static::class);
                $this->seed(\Database\Seeders\DatabaseSeeder::class);
                \Log::info('Finished seeding database for test: ' . static::class);
            } else {
                \Log::info('Database already at latest migration, skipping refresh for test: ' . static::class);
            }
        }
        \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = true;
        self::$migrated = true;
    }

    /**
     * Check if the database needs to be refreshed.
     *
     * @return bool
     */
    protected function needsDatabaseRefresh(): bool
    {
        $shouldRefresh = env('DB_TEST_REFRESH', false);
        if ($shouldRefresh === 'true' || $shouldRefresh === true || $shouldRefresh === '1') {
            \Log::info('DB_TEST_REFRESH environment variable detected, forcing database refresh');
            return true;
        }

        try {
            Artisan::call('migrate:status');
            $output = Artisan::output();
            return str_contains($output, 'Pending') || str_contains($output, 'Migration table not found');
        } catch (\Exception $e) {
            return true;
        }
    }

    /**
     * Assert that two arrays contain the same elements (order-independent).
     * Compares by extracting a key from each element and sorting.
     *
     * @param array $expected Expected array of models/arrays
     * @param array $actual Actual array from response
     * @param callable|null $keyExtractor Function to extract comparison key, defaults to fn($item) => $item['id']
     */
    protected function assertArrayEqualsCanonical(array $expected, array $actual, ?callable $keyExtractor = null): void
    {
        $keyExtractor ??= fn($item) => is_array($item) ? $item['id'] : (string) $item->id;

        $expectedKeys = array_map($keyExtractor, $expected);
        $actualKeys = array_map($keyExtractor, $actual);

        sort($expectedKeys);
        sort($actualKeys);

        $this->assertEquals($expectedKeys, $actualKeys);
    }

    /**
     * Clean up after each test.
     * Resets all API client fakes to prevent state leakage between tests.
     */
    protected function tearDown(): void
    {
        // Clean up all API client fakes to prevent state leakage
        \App\Services\Discord\DiscordApiClient::clearFake();
        \App\Services\NinjaKiwi\NinjaKiwiApiClient::clearFake();

        parent::tearDown();
    }

    /**
     * Create a user with specific format permissions.
     *
     * @param array $permissions Array of [format_id => ['perm1', 'perm2'], null => ['global_perm']]
     *                              format_id can be an integer or null (for global permissions)
     * @return User
     */
    protected function createUserWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();
        $role = Role::factory()->create();
        $user->roles()->attach($role->id);

        foreach ($permissions as $formatId => $perms) {
            foreach ($perms as $permission) {
                RoleFormatPermission::factory()
                    ->for($role)
                    ->permission($permission, $formatId)
                    ->create();
            }
        }

        return $user;
    }

    /**
     * Pick specific keys from an array (supports dot notation for nested keys).
     * Supports wildcard * for array items (e.g., 'players.*.discord_id').
     *
     * @param array $data Source array
     * @param array $keys Keys to pick (supports dot.notation for nested arrays, and * for wildcards)
     * @return array Array with only the picked keys
     */
    protected function pick(array $data, array $keys): array
    {
        $result = [];

        foreach ($keys as $keyPath) {
            $parts = explode('.', $keyPath);
            $this->pickNested($data, $result, $parts);
        }

        return $result;
    }

    /**
     * Recursively pick nested values from array data.
     *
     * @param array $source Source array to extract from
     * @param array &$result Result array to populate
     * @param array $parts Parts of the key path
     * @param string $pathKey Current key path for result (for nested building)
     */
    private function pickNested(array $source, array &$result, array $parts, string $pathKey = ''): void
    {
        $part = $parts[0];
        $isLast = (count($parts) === 1);

        if ($isLast) {
            // Last part - get the value
            if ($part === '*') {
                // Wildcard at the end - copy all values
                foreach ($source as $key => $value) {
                    $result[$key] = $value;
                }
            } elseif (array_key_exists($part, $source)) {
                $result[$part] = $source[$part];
            }
        } else {
            // Not the last part - recurse
            $remainingParts = array_slice($parts, 1);

            if ($part === '*') {
                // Wildcard - apply to all items in the array
                foreach ($source as $key => $value) {
                    if (is_array($value)) {
                        $result[$key] = [];
                        $this->pickNested($value, $result[$key], $remainingParts, $pathKey . $key . '.');
                    }
                }
            } elseif (array_key_exists($part, $source) && is_array($source[$part])) {
                if (!isset($result[$part])) {
                    $result[$part] = [];
                }
                $this->pickNested($source[$part], $result[$part], $remainingParts, $pathKey . $part . '.');
            }
        }
    }

    /**
     * Get all keys from an array except the ones specified (inverse of pick).
     * Supports dot notation for nested keys and wildcards.
     *
     * @param array $data Source array
     * @param array $keys Keys to exclude (supports dot.notation for nested arrays, and * for wildcards)
     * @return array Array with all keys except the ones specified
     */
    protected function except(array $data, array $keys): array
    {
        $result = $data;

        // Process wildcard patterns first (e.g., '*.id')
        $wildcardKeys = array_filter($keys, fn($k) => str_starts_with($k, '*.'));
        $specificKeys = array_diff($keys, $wildcardKeys);

        // Apply wildcards to all items in the array
        foreach ($wildcardKeys as $keyPath) {
            $parts = explode('.', $keyPath);
            // Remove the '*' and process remaining parts for each item
            $remainingParts = array_slice($parts, 1);

            foreach ($result as $key => &$value) {
                if (is_array($value)) {
                    $this->exceptNested($value, $remainingParts);
                }
            }
            unset($value);
        }

        // Apply specific key paths
        foreach ($specificKeys as $keyPath) {
            $parts = explode('.', $keyPath);
            $this->exceptNested($result, $parts);
        }

        return $result;
    }

    /**
     * Recursively remove nested values from array data.
     *
     * @param array &$source Source array to remove from (modified by reference)
     * @param array $parts Parts of the key path to remove
     */
    private function exceptNested(array &$source, array $parts): void
    {
        $part = $parts[0];
        $isLast = (count($parts) === 1);

        if ($isLast) {
            // Last part - remove the key
            unset($source[$part]);
        } else {
            // Not the last part - recurse
            $remainingParts = array_slice($parts, 1);

            if (array_key_exists($part, $source) && is_array($source[$part])) {
                $this->exceptNested($source[$part], $remainingParts);
            }
        }
    }

}
