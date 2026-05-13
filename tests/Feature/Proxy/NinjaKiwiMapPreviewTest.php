<?php

namespace Tests\Feature\Proxy;

use App\Models\Format;
use App\Models\Map;
use App\Models\MapSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NinjaKiwiMapPreviewTest extends TestCase
{
    use RefreshDatabase;

    private string $code = 'TESTCODE';
    private string $nkUrl = 'https://data.ninjakiwi.com/btd6/maps/map/TESTCODE/preview';
    private string $cachePath = 'ninjakiwi_cache/TESTCODE.webp';
    private string $endpoint = '/api/proxy/ninjakiwi/maps/TESTCODE/preview.webp';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Http::preventStrayRequests();
    }

    private function fakePng(): string
    {
        $image = imagecreatetruecolor(1, 1);
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);
        return $png;
    }

    private function fakeNkSuccess(): void
    {
        Http::fake([
            $this->nkUrl => Http::response($this->fakePng(), 200),
        ]);
    }

    // --- Cache hit ---

    #[Group('proxy')]
    public function test_cache_hit_returns_webp_without_db_or_http_call(): void
    {
        Storage::disk('local')->put($this->cachePath, 'fake-webp-data');

        $this->get($this->endpoint)
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'image/webp');

        Http::assertNothingSent();
    }

    // --- DB lookup ---

    #[Group('proxy')]
    public function test_unknown_code_returns_404(): void
    {
        Http::fake();

        $this->getJson($this->endpoint)
            ->assertStatus(404)
            ->assertJson(['error' => 'Map not found']);

        Http::assertNothingSent();
    }

    #[Group('proxy')]
    public function test_code_in_maps_only_proceeds_to_fetch(): void
    {
        Map::factory()->create(['code' => $this->code]);
        $this->fakeNkSuccess();

        $this->get($this->endpoint)->assertStatus(200);
    }

    #[Group('proxy')]
    public function test_code_in_submissions_only_proceeds_to_fetch(): void
    {
        $format = Format::factory()->create();
        $submitter = User::factory()->create();
        MapSubmission::factory()->create([
            'code' => $this->code,
            'format_id' => $format->id,
            'submitter_id' => $submitter->discord_id,
        ]);
        $this->fakeNkSuccess();

        $this->get($this->endpoint)->assertStatus(200);
    }

    // --- NK proxy & caching ---

    #[Group('proxy')]
    public function test_successful_fetch_returns_webp_and_caches(): void
    {
        Map::factory()->create(['code' => $this->code]);
        $this->fakeNkSuccess();

        $this->get($this->endpoint)
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'image/webp');

        Storage::disk('local')->assertExists($this->cachePath);
    }

    #[Group('proxy')]
    public function test_nk_404_is_passed_through_and_not_cached(): void
    {
        Map::factory()->create(['code' => $this->code]);
        Http::fake([$this->nkUrl => Http::response('', 404)]);

        $this->get($this->endpoint)->assertStatus(404);

        Storage::disk('local')->assertMissing($this->cachePath);
    }

    #[Group('proxy')]
    public function test_nk_500_is_passed_through_and_not_cached(): void
    {
        Map::factory()->create(['code' => $this->code]);
        Http::fake([$this->nkUrl => Http::response('', 500)]);

        $this->get($this->endpoint)->assertStatus(500);

        Storage::disk('local')->assertMissing($this->cachePath);
    }

    #[Group('proxy')]
    public function test_nk_returns_invalid_image_body_returns_502(): void
    {
        Map::factory()->create(['code' => $this->code]);
        Http::fake([$this->nkUrl => Http::response('not an image', 200)]);

        $this->get($this->endpoint)->assertStatus(502);

        Storage::disk('local')->assertMissing($this->cachePath);
    }

    #[Group('proxy')]
    public function test_second_request_uses_cache_not_nk(): void
    {
        Map::factory()->create(['code' => $this->code]);
        $this->fakeNkSuccess();

        $this->get($this->endpoint)->assertStatus(200);
        $this->get($this->endpoint)->assertStatus(200);

        Http::assertSentCount(1);
    }
}
