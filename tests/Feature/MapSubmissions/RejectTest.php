<?php

namespace Tests\Feature\MapSubmissions;

use App\Constants\FormatConstants;
use App\Models\Format;
use App\Models\MapSubmission;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;
use Tests\Traits\TestsDiscordAuthMiddleware;
use PHPUnit\Metadata\Group;

class RejectTest extends TestCase
{
    use TestsDiscordAuthMiddleware;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
    }

    protected function endpoint(): string
    {
        return '/api/maps/submissions/1/reject';
    }

    protected function method(): string
    {
        return 'PUT';
    }

    protected function requestData(): array
    {
        return [];
    }

    protected function expectedSuccessStatusCode(): int
    {
        return 204;
    }

    // ========== PERMISSION TESTS ==========

    #[Group('reject')]
    #[Group('map_submissions')]
    public function test_reject_requires_edit_map_submission_permission(): void
    {
        $submission = MapSubmission::factory()->create();
        $user = $this->createUserWithPermissions([]);

        $this->actingAs($user, 'discord')
            ->putJson("/api/maps/submissions/{$submission->id}/reject")
            ->assertStatus(403)
            ->assertJson(['message' => 'Forbidden - You do not have permission to reject submissions for this format.']);
    }

    #[Group('reject')]
    #[Group('map_submissions')]
    public function test_reject_fails_with_wrong_format_permission(): void
    {
        $format1 = Format::find(FormatConstants::MAPLIST);
        $format2 = Format::find(FormatConstants::EXPERT_LIST);

        $submission = MapSubmission::factory()->for($format2)->create();
        $user = $this->createUserWithPermissions([$format1->id => ['edit:map_submission']]);

        $this->actingAs($user, 'discord')
            ->putJson("/api/maps/submissions/{$submission->id}/reject")
            ->assertStatus(403)
            ->assertJson(['message' => 'Forbidden - You do not have permission to reject submissions for this format.']);
    }

    #[Group('reject')]
    #[Group('map_submissions')]
    public function test_reject_succeeds_with_correct_format_permission(): void
    {
        $format = Format::find(FormatConstants::MAPLIST);
        $submission = MapSubmission::factory()
            ->for($format)
            ->pending()
            ->create();

        $admin = $this->createUserWithPermissions([$format->id => ['edit:map_submission']]);

        $this->actingAs($admin, 'discord')
            ->putJson("/api/maps/submissions/{$submission->id}/reject")
            ->assertStatus(204);

        $actual = $this->getJson("/api/maps/submissions/{$submission->id}")
            ->assertStatus(200)
            ->json();

        $this->assertEquals('rejected', $actual['status']);
        $this->assertEquals($admin->discord_id, $actual['rejected_by']);
    }

    #[Group('reject')]
    #[Group('map_submissions')]
    public function test_reject_succeeds_with_global_permission(): void
    {
        $submission = MapSubmission::factory()->pending()->create();
        $admin = $this->createUserWithPermissions([null => ['edit:map_submission']]);

        $this->actingAs($admin, 'discord')
            ->putJson("/api/maps/submissions/{$submission->id}/reject")
            ->assertStatus(204);

        $actual = $this->getJson("/api/maps/submissions/{$submission->id}")
            ->assertStatus(200)
            ->json();

        $this->assertEquals('rejected', $actual['status']);
        $this->assertEquals($admin->discord_id, $actual['rejected_by']);
    }

    // ========== PENDING TESTS ==========

    #[Group('reject')]
    #[Group('map_submissions')]
    public function test_reject_fails_if_already_rejected(): void
    {
        $format = Format::find(FormatConstants::MAPLIST);

        $submission = MapSubmission::factory()
            ->for($format)
            ->rejected()
            ->create();

        $user = $this->createUserWithPermissions([$format->id => ['edit:map_submission']]);

        $this->actingAs($user, 'discord')
            ->putJson("/api/maps/submissions/{$submission->id}/reject")
            ->assertStatus(422)
            ->assertJson(['message' => 'Cannot reject a submission that has already been processed.']);
    }

    #[Group('reject')]
    #[Group('map_submissions')]
    public function test_reject_fails_if_already_accepted(): void
    {
        $format = Format::find(FormatConstants::MAPLIST);

        $submission = MapSubmission::factory()
            ->for($format)
            ->accepted()
            ->create();

        $user = $this->createUserWithPermissions([$format->id => ['edit:map_submission']]);

        $this->actingAs($user, 'discord')
            ->putJson("/api/maps/submissions/{$submission->id}/reject")
            ->assertStatus(422)
            ->assertJson(['message' => 'Cannot reject a submission that has already been processed.']);
    }

    #[Group('reject')]
    #[Group('map_submissions')]
    public function test_reject_succeeds_for_pending_submission(): void
    {
        $format = Format::find(FormatConstants::MAPLIST);
        $user = User::factory()->create();

        $submission = MapSubmission::factory()
            ->for($format)
            ->for($user, 'submitter')
            ->pending()
            ->create();

        $admin = $this->createUserWithPermissions([$format->id => ['edit:map_submission']]);

        $this->actingAs($admin, 'discord')
            ->putJson("/api/maps/submissions/{$submission->id}/reject")
            ->assertStatus(204);

        // Verify rejected status using GET (not database query)
        $actual = $this->getJson("/api/maps/submissions/{$submission->id}")
            ->assertStatus(200)
            ->json();

        $this->assertEquals('rejected', $actual['status']);
        $this->assertEquals($admin->discord_id, $actual['rejected_by']);
    }

    // ========== NOT FOUND TESTS ==========

    #[Group('reject')]
    #[Group('map_submissions')]
    public function test_reject_returns_404_if_not_found(): void
    {
        $user = $this->createUserWithPermissions([null => ['edit:map_submission']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/maps/submissions/999999/reject')
            ->assertStatus(404)
            ->assertJson(['message' => 'Not Found']);
    }
}
