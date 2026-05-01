<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            \Database\Seeders\Core\RetroGameSeeder::class,
            \Database\Seeders\Core\FormatSeeder::class,
            \Database\Seeders\Core\FormatRuleSubsetSeeder::class,
            \Database\Seeders\Core\RoleSeeder::class,
            \Database\Seeders\Core\ConfigSeeder::class,
        ]);
    }
}
