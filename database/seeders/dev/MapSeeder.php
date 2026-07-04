<?php

namespace Database\Seeders\Dev;

use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\RetroMap;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Faker\Factory;

class MapSeeder extends Seeder
{
    use WithoutModelEvents;

    private const int PAGES_TO_FETCH = 4;

    private const int MAPS_WITH_META = 60;

    /** @var array<int, array{code: string, name: string}> */
    private array $mapsToInsert = [];

    private \Faker\Generator $faker;

    public function __construct()
    {
        $this->faker = Factory::create();
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->fetchFromEndpoint('newest');
        $this->fetchFromEndpoint('mostLiked');

        $this->bulkInsertMaps();
        $this->createMapListMeta();
    }

    /**
     * Fetch maps from a specific endpoint.
     */
    private function fetchFromEndpoint(string $endpoint): void
    {
        $url = "https://data.ninjakiwi.com/btd6/maps/filter/{$endpoint}";

        for ($page = 1; $page <= self::PAGES_TO_FETCH; $page++) {
            $this->command->info("Fetching {$endpoint} page {$page}...");

            $response = Http::withOptions(['debug' => false])
                ->get($url, ['page' => $page]);

            if (!$response->successful()) {
                $this->command->error("Failed to fetch {$endpoint} page {$page}: {$response->status()}");
                continue;
            }

            $data = $response->json();

            if (!($data['success'] ?? false)) {
                $this->command->error("API returned unsuccessful for {$endpoint} page {$page}");
                continue;
            }

            $maps = $data['body'] ?? [];
            foreach ($maps as $mapData) {
                $code = $mapData['id'] ?? null;
                $name = $mapData['name'] ?? null;

                if ($code && $name) {
                    $this->mapsToInsert[] = [
                        'code' => $code,
                        'name' => $name,
                    ];
                }
            }

            $this->command->info("Processed " . count($maps) . " maps from {$endpoint} page {$page}");
        }
    }

    /**
     * Bulk insert all maps using a single upsert query.
     */
    private function bulkInsertMaps(): void
    {
        if (empty($this->mapsToInsert)) {
            $this->command->warn("No maps to insert");
            return;
        }
        $this->command->info("Bulk inserting " . count($this->mapsToInsert) . " maps...");

        $payloads = array_map(fn($map) => [
            'code' => $map['code'],
            'name' => $map['name'],
            'r6_start' => null,
            'map_data' => null,
            'map_preview_url' => null,
            'map_notes' => $this->faker->sentence(),
        ], $this->mapsToInsert);

        Map::upsert(
            $payloads,
            ['code'],
            ['name', 'r6_start', 'map_data', 'map_preview_url', 'map_notes']
        );

        $this->command->info("Bulk insert complete.");
    }

    /**
     * Create MapListMeta entries for the maps.
     */
    private function createMapListMeta(): void
    {
        $mapCodes = array_column($this->mapsToInsert, 'code');
        $this->command->info("Creating MapListMeta for " . count($mapCodes) . " maps...");

        // Get available retro map IDs
        $availableRetroMapIds = RetroMap::inRandomOrder()->limit(count($this->mapsToInsert))->pluck('id');
        $remakeCount = 0;

        // Build all meta entries first
        $metaEntries = [];
        foreach ($mapCodes as $index => $code) {
            $remakeOf = null;

            // 30% chance to assign a retro map as remake
            if ($this->faker->boolean(30) && $availableRetroMapIds->isNotEmpty()) {
                $remakeOf = $availableRetroMapIds->pop();
                $remakeCount++;
            }

            $metaEntries[$code] = [
                'code' => $code,
                'placement_curver' => null,
                'placement_allver' => null,
                'difficulty' => $index % 5,
                'optimal_heros' => $this->faker->randomElements(
                    ['Quincy', 'Gwendolin', 'Striker Jones', 'Obyn', 'Benjamin', 'Ezili', 'Pat Fusty', 'Adora', 'Churchill', 'Etienne', 'Sauda', 'Dan'],
                    $this->faker->numberBetween(0, 3)
                ),
                'botb_difficulty' => $index % 5,
                'remake_of' => $remakeOf,
                'created_on' => now(),
                'deleted_on' => null,
            ];
        }

        $mapsToProcess = array_slice($mapCodes, 0, self::MAPS_WITH_META);
        // Set placement_curver for all maps
        foreach ($mapsToProcess as $index => $code) {
            $metaEntries[$code]['placement_curver'] = $index + 1;
            if ($index >= 10) {
                $metaEntries[$code]['placement_allver'] = $index - 10 + 1;
            }
        }

        $this->command->info("Assigned {$remakeCount} retro maps as remakes.");

        // Update/create all entries
        foreach ($metaEntries as $code => $meta) {
            MapListMeta::updateOrCreate(
                [
                    'code' => $code,
                    'deleted_on' => null,
                ],
                $meta
            );
        }

        $this->command->info("Created MapListMeta entries.");
    }
}
