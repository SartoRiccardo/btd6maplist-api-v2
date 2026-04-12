<?php

namespace Database\Seeders\Dev;

use App\Models\Format;
use App\Models\MapSubmission;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MapSubmissionSeeder extends Seeder
{
    use WithoutModelEvents;

    private \Faker\Generator $faker;

    public function __construct()
    {
        $this->faker = \Faker\Factory::create();
    }

    public function run(): void
    {
        $formats = Format::all();
        $users = User::inRandomOrder()->limit(50)->get();

        if ($formats->isEmpty()) {
            $this->command->error('No formats found. Please seed formats first.');
            return;
        }

        if ($users->isEmpty()) {
            $this->command->error('No users found. Please seed users first.');
            return;
        }

        for ($i = 0; $i < 30; $i++) {
            $format = $formats->random();
            $submitter = $users->random();
            $createdOn = Carbon::instance($this->faker->dateTimeBetween('-6 months', 'now'));

            // 50% pending, 30% rejected, 20% accepted
            $roll = $this->faker->numberBetween(1, 100);

            $state = match (true) {
                $roll <= 50 => 'pending',
                $roll <= 80 => 'rejected',
                default => 'accepted',
            };

            $submission = MapSubmission::factory()->{$state}();

            if ($state === 'rejected') {
                $submission = $submission->state([
                    'rejected_by' => $users->random()->discord_id,
                ]);
            }

            $submission->create([
                'submitter_id' => $submitter->discord_id,
                'format_id' => $format->id,
                'proposed' => $this->faker->numberBetween(1, 50),
                'subm_notes' => $this->faker->boolean(40) ? $this->faker->sentence() : null,
                'created_on' => $createdOn,
            ]);
        }

        $this->command->info('Map submissions seeded successfully.');
    }
}
