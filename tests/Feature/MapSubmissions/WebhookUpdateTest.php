<?php

namespace Tests\Feature\MapSubmissions;

use App\Constants\FormatConstants;
use App\Jobs\UpdateMapSubmissionWebhookJob;
use App\Models\Config;
use App\Models\Format;
use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\MapSubmission;
use App\Models\RetroMap;
use App\Models\User;
use App\Services\Discord\DiscordWebhookClient;
use App\Services\NinjaKiwi\NinjaKiwiApiClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;
use PHPUnit\Metadata\Group;

class WebhookUpdateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        DiscordWebhookClient::fake(true, '123456789');
        NinjaKiwiApiClient::fakeMapExists([]);
    }

    protected function tearDown(): void
    {
        DiscordWebhookClient::clearFake();
        NinjaKiwiApiClient::clearFake();
        parent::tearDown();
    }

    // ========== REJECT WEBHOOK TESTS ==========

    #[Group('webhook')]
    #[Group('map_submissions')]
    public function test_reject_map_submission_dispatches_update_job(): void
    {
        Bus::fake();

        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map_submission']]);
        $map = Map::factory()->create();
        $format = Format::find(FormatConstants::MAPLIST);
        $format->map_submission_wh = 'https://discord.com/api/webhooks/test/webhook';
        $format->save();

        $submission = MapSubmission::factory()->pending()->create([
            'code' => $map->code,
            'format_id' => $format->id,
            'wh_msg_id' => '123456',
            'wh_data' => json_encode(['embeds' => [['color' => 0x1e88e5]]]),
        ]);

        $this->actingAs($user, 'discord')
            ->putJson("/api/maps/submissions/{$submission->id}/reject")
            ->assertStatus(204);

        Bus::assertDispatched(UpdateMapSubmissionWebhookJob::class, function ($job) use ($submission) {
            $this->assertEquals($submission->id, $job->submissionId);
            $this->assertTrue($job->fail);
            return true;
        });

        $submission->refresh();
        $this->assertNotNull($submission->rejected_by);
    }

    #[Group('webhook')]
    #[Group('map_submissions')]
    public function test_reject_map_submission_skips_job_if_no_webhook_data(): void
    {
        Bus::fake();

        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map_submission']]);
        $map = Map::factory()->create();
        $format = Format::find(FormatConstants::MAPLIST);
        $format->save();

        $submission = MapSubmission::factory()->pending()->create([
            'code' => $map->code,
            'format_id' => $format->id,
            'wh_msg_id' => null, // No webhook data
        ]);

        $this->actingAs($user, 'discord')
            ->putJson("/api/maps/submissions/{$submission->id}/reject")
            ->assertStatus(204);

        Bus::assertNotDispatched(UpdateMapSubmissionWebhookJob::class);
    }

    // ========== IMPLICIT ACCEPTANCE WEBHOOK TESTS ==========

    #[Group('webhook')]
    #[Group('map_submissions')]
    public function test_post_map_with_submission_on_same_format_dispatches_green_job_and_links_meta(): void
    {
        Bus::fake();

        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);
        $mapCode = 'TEST' . rand(1000, 9999);
        $format = Format::find(FormatConstants::MAPLIST);
        $format->map_submission_wh = 'https://discord.com/api/webhooks/test/webhook';
        $format->save();

        // Create pending submission
        $submission = MapSubmission::factory()->pending()->create([
            'code' => $mapCode,
            'format_id' => $format->id,
            'wh_msg_id' => '123456',
            'wh_data' => json_encode(['embeds' => [['color' => 0x1e88e5]]]),
        ]);

        // Ensure map_count config exists
        Config::factory()->create([
            'name' => 'map_count',
            'value' => '50',
            'type' => 'int',
        ]);

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', [
                'code' => $mapCode,
                'name' => 'Test Map',
                'placement_curver' => 1,
            ])
            ->assertStatus(201);

        Bus::assertDispatched(UpdateMapSubmissionWebhookJob::class, function ($job) use ($submission) {
            $this->assertEquals($submission->id, $job->submissionId);
            $this->assertFalse($job->fail);
            return true;
        });

        $submission->refresh();
        $this->assertNotNull($submission->accepted_meta_id);
    }

    #[Group('webhook')]
    #[Group('map_submissions')]
    public function test_post_map_with_submission_on_different_format_does_nothing(): void
    {
        Bus::fake();

        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map'], FormatConstants::EXPERT_LIST => ['edit:map']]);
        $mapCode = 'TEST' . rand(1000, 9999);
        $maplistFormat = Format::find(FormatConstants::MAPLIST);
        $expertFormat = Format::find(FormatConstants::EXPERT_LIST);

        $maplistFormat->map_submission_wh = 'https://discord.com/api/webhooks/test/webhook';
        $maplistFormat->save();
        $expertFormat->save();

        // Create pending submission for MAPLIST
        $submission = MapSubmission::factory()->pending()->create([
            'code' => $mapCode,
            'format_id' => $maplistFormat->id,
            'wh_msg_id' => '123456',
            'wh_data' => json_encode(['embeds' => [['color' => 0x1e88e5]]]),
        ]);

        // Create map for EXPERT format (difficulty 1 = Expert)
        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', [
                'code' => $mapCode,
                'name' => 'Test Map',
                'difficulty' => 1,
            ])
            ->assertStatus(201);

        Bus::assertNotDispatched(UpdateMapSubmissionWebhookJob::class);

        $submission->refresh();
        $this->assertNull($submission->accepted_meta_id);
    }

    /**
     * Crackhead test case, should never happen. We have a map already in a list and, somehow,
     * a pending completion for that list for that map. If we edit that map to still be in the list,
     * I suppose it's right for it to be marked as accepted.
     * 
     * Cool edge case I didn't think about.
     */
    /**
     * Map is already on the list in a format, and we update that same format's value.
     * Should NOT dispatch accept submission job (only triggers on newly added formats).
     */
    #[Group('webhook')]
    #[Group('map_submissions')]
    public function test_put_map_with_submission_on_same_format_dispatches_green_job_and_links_meta(): void
    {
        Bus::fake();
        $now = Carbon::now();

        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);
        $map = Map::factory()->create();
        $format = Format::find(FormatConstants::MAPLIST);
        $format->map_submission_wh = 'https://discord.com/api/webhooks/test/webhook';
        $format->save();

        // Create existing meta
        MapListMeta::factory()->create([
            'code' => $map->code,
            'placement_curver' => 10,
            'created_on' => $now->copy()->subHour(),
        ]);

        // Create pending submission
        $submission = MapSubmission::factory()->pending()->create([
            'code' => $map->code,
            'format_id' => $format->id,
            'wh_msg_id' => '123456',
            'wh_data' => json_encode(['embeds' => [['color' => 0x1e88e5]]]),
        ]);

        // Ensure map_count config exists
        Config::factory()->create([
            'name' => 'map_count',
            'value' => '50',
            'type' => 'int',
        ]);

        $this->actingAs($user, 'discord')
            ->putJson("/api/maps/{$map->code}", [
                'name' => 'Updated Map',
                'placement_curver' => 5,
            ])
            ->assertStatus(204);

        // Should NOT dispatch because format was already on the list
        Bus::assertNotDispatched(UpdateMapSubmissionWebhookJob::class);

        $submission->refresh();
        $this->assertNull($submission->accepted_meta_id);
    }

    #[Group('webhook')]
    #[Group('map_submissions')]
    public function test_put_maplist_map_with_placement_above_count_skips_job_and_link(): void
    {
        Bus::fake();

        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);
        $mapCode = 'TEST' . rand(1000, 9999);
        $format = Format::find(FormatConstants::MAPLIST);
        $format->map_submission_wh = 'https://discord.com/api/webhooks/test/webhook';
        $format->save();

        // First, create a map at position 1 to establish the map_count
        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', [
                'code' => $mapCode . 'X',
                'name' => 'First Map',
                'placement_curver' => 1,
            ])
            ->assertStatus(201);

        $submission = MapSubmission::factory()->pending()->create([
            'code' => $mapCode,
            'format_id' => $format->id,
            'wh_msg_id' => '123456',
            'wh_data' => json_encode(['embeds' => [['color' => 0x1e88e5]]]),
        ]);

        // Ensure map_count config exists with value 1
        Config::factory()->create([
            'name' => 'map_count',
            'value' => '1',
            'type' => 'int',
        ]);

        // Create map with placement 2 (above map_count of 1)
        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', [
                'code' => $mapCode,
                'name' => 'Test Map',
                'placement_curver' => 2,
            ])
            ->assertStatus(201);

        Bus::assertNotDispatched(UpdateMapSubmissionWebhookJob::class);

        $submission->refresh();
        $this->assertNull($submission->accepted_meta_id);
    }

    #[Group('webhook')]
    #[Group('map_submissions')]
    public function test_put_map_with_submission_on_different_format_does_nothing(): void
    {
        Bus::fake();
        $now = Carbon::now();

        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map'], FormatConstants::EXPERT_LIST => ['edit:map']]);
        $map = Map::factory()->create();
        $maplistFormat = Format::find(FormatConstants::MAPLIST);
        $expertFormat = Format::find(FormatConstants::EXPERT_LIST);

        $maplistFormat->map_submission_wh = 'https://discord.com/api/webhooks/test/webhook';
        $maplistFormat->save();
        $expertFormat->save();

        // Create existing meta with MAPLIST placement
        MapListMeta::factory()->create([
            'code' => $map->code,
            'placement_curver' => 10,
            'created_on' => $now->copy()->subHour(),
        ]);

        // Create pending submission for MAPLIST format
        $submission = MapSubmission::factory()->pending()->create([
            'code' => $map->code,
            'format_id' => $maplistFormat->id,
            'wh_msg_id' => '123456',
            'wh_data' => json_encode(['embeds' => [['color' => 0x1e88e5]]]),
        ]);

        // Update map to add EXPERT_LIST (different format than submission)
        $this->actingAs($user, 'discord')
            ->putJson("/api/maps/{$map->code}", [
                'name' => 'Updated Map',
                'difficulty' => 1,
            ])
            ->assertStatus(204);

        // Should NOT dispatch job because submission is for MAPLIST, not EXPERT_LIST
        Bus::assertNotDispatched(UpdateMapSubmissionWebhookJob::class);

        $submission->refresh();
        $this->assertNull($submission->accepted_meta_id);
    }

    // ========== NON-PENDING MAP SUBMISSION WEBHOOK TESTS ==========

    #[Group('webhook')]
    #[Group('map_submissions')]
    public function test_post_map_does_not_dispatch_submission_webhooks_for_non_pending_submissions(): void
    {
        Bus::fake();

        $user = $this->createUserWithPermissions([FormatConstants::EXPERT_LIST => ['edit:map']]);
        $mapCode = 'TEST' . rand(1000, 9999);
        $format = Format::find(FormatConstants::EXPERT_LIST);
        $format->map_submission_wh = 'https://discord.com/api/webhooks/test/webhook';
        $format->save();

        // Create an accepted submission

        MapSubmission::factory()->create([
            'code' => $mapCode,
            'format_id' => $format->id,
            'wh_msg_id' => 123456789,
            'wh_data' => json_encode(['embeds' => [['color' => 0x43a047]]]),
            'accepted_meta_id' => MapListMeta::factory()->create(),  // No clue how this would happen
        ]);

        // Create a rejected submission
        MapSubmission::factory()->create([
            'code' => $mapCode,
            'format_id' => $format->id,
            'wh_msg_id' => 987654321,
            'wh_data' => json_encode(['embeds' => [['color' => 0xb71c1c]]]),
            'rejected_by' => User::factory()->create()->discord_id,
        ]);

        // POST to add map to Expert List - this should NOT dispatch webhook jobs for non-pending submissions
        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', [
                'code' => $mapCode,
                'name' => 'Test Map',
                'difficulty' => 2,
            ])
            ->assertStatus(201);

        // Should NOT dispatch map submission webhook jobs for non-pending submissions
        Bus::assertNotDispatched(UpdateMapSubmissionWebhookJob::class);
    }

    #[Group('webhook')]
    #[Group('map_submissions')]
    public function test_put_map_does_not_dispatch_submission_webhooks_for_non_pending_submissions(): void
    {
        Bus::fake();
        $now = Carbon::now();

        $user = $this->createUserWithPermissions([FormatConstants::EXPERT_LIST => ['edit:map']]);
        $map = Map::factory()->create();
        $format = Format::find(FormatConstants::EXPERT_LIST);
        $format->map_submission_wh = 'https://discord.com/api/webhooks/test/webhook';
        $format->save();

        // Create existing meta without difficulty (null) - map exists but not on Expert List
        MapListMeta::factory()->create([
            'code' => $map->code,
            'difficulty' => null,
            'created_on' => $now->copy()->subHour(),
        ]);

        MapSubmission::factory()->create([
            'code' => $map->code,
            'format_id' => $format->id,
            'wh_msg_id' => 111111111,
            'wh_data' => json_encode(['embeds' => [['color' => 0x43a047]]]),
            'accepted_meta_id' => MapListMeta::factory()->create(),
        ]);

        // Create a rejected submission
        MapSubmission::factory()->create([
            'code' => $map->code,
            'format_id' => $format->id,
            'wh_msg_id' => 222222222,
            'wh_data' => json_encode(['embeds' => [['color' => 0xb71c1c]]]),
            'rejected_by' => User::factory()->create()->discord_id,
        ]);

        // PUT to add map to Expert List - this should NOT dispatch webhook jobs for non-pending submissions
        $this->actingAs($user, 'discord')
            ->putJson("/api/maps/{$map->code}", [
                'name' => 'Updated Map',
                'difficulty' => 2,
            ])
            ->assertStatus(204);

        // Should NOT dispatch map submission webhook jobs for non-pending submissions
        Bus::assertNotDispatched(UpdateMapSubmissionWebhookJob::class);
    }
}
