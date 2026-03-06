<?php

namespace Tests\Feature\MapSubmissions;

use App\Models\MapSubmission;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\TestsDiscordAuthMiddleware;
use PHPUnit\Metadata\Group;

class DeleteTest extends TestCase
{
    use TestsDiscordAuthMiddleware;

    protected function endpoint(): string
    {
        return '/api/maps/submissions/1';
    }

    protected function method(): string
    {
        return 'DELETE';
    }

    protected function requestData(): array
    {
        return [];
    }

    protected function expectedSuccessStatusCode(): int
    {
        return 204;
    }

    // ========== OWNER TESTS ==========

    #[Group('delete')]
    #[Group('map_submissions')]
    public function test_delete_fails_if_not_owner(): void
    {
        $submission = MapSubmission::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser, 'discord')
            ->deleteJson("/api/maps/submissions/{$submission->id}")
            ->assertStatus(403)
            ->assertJson(['message' => 'Forbidden - You can only delete your own submissions.']);

        // Verify submission still exists using GET
        $this->getJson("/api/maps/submissions/{$submission->id}")
            ->assertStatus(200)
            ->assertJson(['id' => $submission->id]);
    }

    #[Group('delete')]
    #[Group('map_submissions')]
    public function test_delete_succeeds_for_owner(): void
    {
        $user = User::factory()->create();
        $submission = MapSubmission::factory()->for($user, 'submitter')->create();

        Storage::fake('public');

        $this->actingAs($user, 'discord')
            ->deleteJson("/api/maps/submissions/{$submission->id}")
            ->assertStatus(204);

        // Verify submission was deleted using GET
        $this->getJson("/api/maps/submissions/{$submission->id}")
            ->assertStatus(404)
            ->assertJson(['message' => 'Not Found']);
    }

    // ========== PENDING TESTS ==========

    #[Group('delete')]
    #[Group('map_submissions')]
    public function test_delete_fails_if_already_rejected(): void
    {
        $user = User::factory()->create();
        $submission = MapSubmission::factory()
            ->for($user, 'submitter')
            ->rejected()
            ->create();

        $this->actingAs($user, 'discord')
            ->deleteJson("/api/maps/submissions/{$submission->id}")
            ->assertStatus(422)
            ->assertJson(['message' => 'Cannot delete a submission that has already been processed.']);
    }

    #[Group('delete')]
    #[Group('map_submissions')]
    public function test_delete_fails_if_already_accepted(): void
    {
        $user = User::factory()->create();
        $submission = MapSubmission::factory()
            ->for($user, 'submitter')
            ->accepted()
            ->create();

        $this->actingAs($user, 'discord')
            ->deleteJson("/api/maps/submissions/{$submission->id}")
            ->assertStatus(422)
            ->assertJson(['message' => 'Cannot delete a submission that has already been processed.']);
    }

    #[Group('delete')]
    #[Group('map_submissions')]
    public function test_delete_succeeds_for_pending_submission(): void
    {
        $user = User::factory()->create();
        $submission = MapSubmission::factory()
            ->for($user, 'submitter')
            ->pending()
            ->create();

        Storage::fake('public');

        $this->actingAs($user, 'discord')
            ->deleteJson("/api/maps/submissions/{$submission->id}")
            ->assertStatus(204);

        // Verify the submission was deleted using GET (not database query)
        $this->getJson("/api/maps/submissions/{$submission->id}")
            ->assertStatus(404)
            ->assertJson(['message' => 'Not Found']);
    }

    // ========== IMAGE CLEANUP TESTS ==========

    #[Group('delete')]
    #[Group('map_submissions')]
    public function test_delete_removes_image_from_storage(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $submission = MapSubmission::factory()
            ->for($user, 'submitter')
            ->pending()
            ->create();

        // Create a fake image file
        Storage::disk('public')->put($submission->completion_proof, 'fake-image-content');

        $this->actingAs($user, 'discord')
            ->deleteJson("/api/maps/submissions/{$submission->id}")
            ->assertStatus(204);

        Storage::disk('public')->assertMissing($submission->completion_proof);
    }

    // ========== NOT FOUND TESTS ==========

    #[Group('delete')]
    #[Group('map_submissions')]
    public function test_delete_returns_404_if_not_found(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'discord')
            ->deleteJson('/api/maps/submissions/999999')
            ->assertStatus(404)
            ->assertJson(['message' => 'Not Found']);
    }
}
