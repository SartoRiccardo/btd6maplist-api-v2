<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DevSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        \DB::transaction(function () {
            $this->call([
                \Database\Seeders\DatabaseSeeder::class,
                \Database\Seeders\Dev\FormatSeeder::class,
                // Users
                \Database\Seeders\Dev\UserSeeder::class,
                \Database\Seeders\Dev\UserRoleSeeder::class,
                // Maps
                \Database\Seeders\Dev\RetroSeeder::class,
                \Database\Seeders\Dev\MapSeeder::class,
                \Database\Seeders\Dev\CreatorSeeder::class,
                \Database\Seeders\Dev\VerificationSeeder::class,
                \Database\Seeders\Dev\AdditionalCodeSeeder::class,
                // Completions
                \Database\Seeders\Dev\CompletionSeeder::class,
                // Map Submissions
                \Database\Seeders\Dev\MapSubmissionSeeder::class,
                // Achievement Roles
                \Database\Seeders\Dev\AchievementRoleSeeder::class,
            ]);
        });
    }
}
