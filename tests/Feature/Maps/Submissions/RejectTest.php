<?php

namespace Tests\Feature\Maps\Submissions;

use App\Models\Format;
use App\Models\MapSubmission;
use App\Models\Role;
use App\Models\RoleFormatPermission;
use App\Models\User;
use Tests\TestCase;

class RejectTest extends TestCase
{
    protected User $admin;
    protected User $regularUser;
    protected Format $format;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->regularUser = User::factory()->create();
        $this->format = Format::factory()->create();
    }

    /**
     * Test that reject requires Discord auth.
     */
    public function test_reject_requires_discord_auth(): void
    {
        $submission = MapSubmission::factory()->create([
            'format_id' => $this->format->id,
            'rejected_by' => null,
            'accepted_meta_id' => null,
        ]);

        $response = $this->putJson("/api/maps/submissions/{$submission->id}/reject");

        $response->assertStatus(401);
    }

    /**
     * Test that reject fails if user lacks permission.
     */
    public function test_reject_requires_admin_permission(): void
    {
        $submission = MapSubmission::factory()->create([
            'format_id' => $this->format->id,
            'rejected_by' => null,
            'accepted_meta_id' => null,
        ]);

        $response = $this->actingAs($this->regularUser, 'discord')
            ->putJson("/api/maps/submissions/{$submission->id}/reject");

        $response->assertStatus(403);
    }

    /**
     * Test that reject fails if submission is not pending.
     */
    public function test_reject_fails_if_not_pending(): void
    {
        // Setup admin with permission
        $role = Role::factory()->create();
        $this->admin->roles()->attach($role);
        RoleFormatPermission::factory()->create([
            'role_id' => $role->id,
            'format_id' => $this->format->id,
            'permission' => 'edit:map_submission',
        ]);

        $submission = MapSubmission::factory()->create([
            'format_id' => $this->format->id,
            'rejected_by' => '123456789',  // Already rejected
        ]);

        $response = $this->actingAs($this->admin, 'discord')
            ->putJson("/api/maps/submissions/{$submission->id}/reject");

        $response->assertStatus(422);
        $response->assertJson(['message' => 'This submission has already been processed.']);
    }

    /**
     * Test that reject fails if submission is already accepted.
     */
    public function test_reject_fails_if_already_accepted(): void
    {
        // Setup admin with permission
        $role = Role::factory()->create();
        $this->admin->roles()->attach($role);
        RoleFormatPermission::factory()->create([
            'role_id' => $role->id,
            'format_id' => $this->format->id,
            'permission' => 'edit:map_submission',
        ]);

        $submission = MapSubmission::factory()->create([
            'format_id' => $this->format->id,
            'accepted_meta_id' => 1,  // Already accepted
        ]);

        $response = $this->actingAs($this->admin, 'discord')
            ->putJson("/api/maps/submissions/{$submission->id}/reject");

        $response->assertStatus(422);
    }

    /**
     * Test that reject returns 404 for non-existent submission.
     */
    public function test_reject_returns_404_if_not_found(): void
    {
        // Setup admin with permission
        $role = Role::factory()->create();
        $this->admin->roles()->attach($role);
        RoleFormatPermission::factory()->create([
            'role_id' => $role->id,
            'format_id' => $this->format->id,
            'permission' => 'edit:map_submission',
        ]);

        $response = $this->actingAs($this->admin, 'discord')
            ->putJson('/api/maps/submissions/99999/reject');

        $response->assertStatus(404);
    }

    /**
     * Test that reject successfully sets rejected_by.
     */
    public function test_reject_successfully_sets_rejected_by(): void
    {
        // Setup admin with permission
        $role = Role::factory()->create();
        $this->admin->roles()->attach($role);
        RoleFormatPermission::factory()->create([
            'role_id' => $role->id,
            'format_id' => $this->format->id,
            'permission' => 'edit:map_submission',
        ]);

        $submission = MapSubmission::factory()->create([
            'format_id' => $this->format->id,
            'rejected_by' => null,
            'accepted_meta_id' => null,
        ]);

        $response = $this->actingAs($this->admin, 'discord')
            ->putJson("/api/maps/submissions/{$submission->id}/reject");

        $response->assertStatus(204);

        $submission->refresh();
        $this->assertEquals($this->admin->discord_id, $submission->rejected_by);
        $this->assertNull($submission->accepted_meta_id);
    }

    /**
     * Test that admin with global permission can reject any format.
     */
    public function test_reject_with_global_permission(): void
    {
        // Setup admin with global permission (permission on null format_id)
        $role = Role::factory()->create();
        $this->admin->roles()->attach($role);
        RoleFormatPermission::factory()->create([
            'role_id' => $role->id,
            'format_id' => null,  // Global permission
            'permission' => 'edit:map_submission',
        ]);

        $submission = MapSubmission::factory()->create([
            'format_id' => $this->format->id,
            'rejected_by' => null,
            'accepted_meta_id' => null,
        ]);

        $response = $this->actingAs($this->admin, 'discord')
            ->putJson("/api/maps/submissions/{$submission->id}/reject");

        $response->assertStatus(204);

        $submission->refresh();
        $this->assertEquals($this->admin->discord_id, $submission->rejected_by);
    }
}
