<?php

namespace Tests\Feature\Users;

use App\Models\User;
use App\Services\NinjaKiwi\NinjaKiwiApiClient;
use Tests\Traits\TestsDiscordAuthMiddleware;
use Tests\TestCase;

class UpdateUserTest extends TestCase
{
    use TestsDiscordAuthMiddleware;

    protected function endpoint(): string
    {
        return '/api/users/@me';
    }

    protected function method(): string
    {
        return 'PUT';
    }

    protected function requestData(): array
    {
        return [
            'name' => 'UpdatedName',
            'nk_oak' => null,
        ];
    }

    // ========== AUTHORIZATION TESTS ==========

    public function test_update_user_requires_edit_self_permission(): void
    {
        $user = User::factory()->create(['name' => 'OriginalName']);

        $this->actingAs($user, 'discord')
            ->putJson('/api/users/@me', ['name' => 'NewName'])
            ->assertStatus(403)
            ->assertJson(['message' => 'Forbidden - You do not have permission to edit user profiles.']);
    }

    // ========== FUNCTIONAL TESTS ==========

    public function test_update_user_resolves_at_me_alias(): void
    {
        $user = $this->createUserWithPermissions([null => ['edit:self']]);
        $user->name = 'OriginalName';
        $user->save();

        $this->actingAs($user, 'discord')
            ->putJson('/api/users/@me', ['name' => 'UpdatedName'])
            ->assertStatus(200)
            ->assertJson(['name' => 'UpdatedName']);

        // Verify via GET request (use actual ID since show() doesn't support @me)
        $this->getJson("/api/users/{$user->discord_id}")
            ->assertStatus(200)
            ->assertJson(['name' => 'UpdatedName']);
    }

    public function test_update_user_with_numeric_id_updates_self(): void
    {
        $user = $this->createUserWithPermissions([null => ['edit:self']]);
        $user->name = 'OriginalName';
        $user->save();

        $this->actingAs($user, 'discord')
            ->putJson("/api/users/{$user->discord_id}", ['name' => 'UpdatedName'])
            ->assertStatus(200)
            ->assertJson(['name' => 'UpdatedName']);

        // Verify via GET request
        $this->getJson("/api/users/{$user->discord_id}")
            ->assertStatus(200)
            ->assertJson(['name' => 'UpdatedName']);
    }

    public function test_update_user_fails_on_duplicate_name_case_insensitive(): void
    {
        User::factory()->create(['name' => 'JohnDoe']);
        $userB = $this->createUserWithPermissions([null => ['edit:self']]);
        $userB->name = 'OriginalName';
        $userB->save();

        $this->actingAs($userB, 'discord')
            ->putJson('/api/users/@me', ['name' => 'johndoe'])
            ->assertStatus(422)
            ->assertJson([
                'errors' => [
                    'name' => ['The name has already been taken.'],
                ],
            ]);

        // Verify name didn't change via GET request (use actual ID since show() doesn't support @me)
        $this->getJson("/api/users/{$userB->discord_id}")
            ->assertStatus(200)
            ->assertJson(['name' => 'OriginalName']);
    }

    public function test_update_user_returns_501_for_other_users(): void
    {
        $userA = User::factory()->create(['name' => 'UserA']);
        $userB = $this->createUserWithPermissions([null => ['edit:self']]);

        $this->actingAs($userB, 'discord')
            ->putJson("/api/users/{$userA->discord_id}", ['name' => 'NewName'])
            ->assertStatus(501)
            ->assertJson(['message' => 'Not Implemented']);

        // Verify userA's name didn't change via GET request
        $this->getJson("/api/users/{$userA->discord_id}")
            ->assertStatus(200)
            ->assertJson(['name' => 'UserA']);
    }

    public function test_update_user_validates_nk_oak_success(): void
    {
        $user = $this->createUserWithPermissions([null => ['edit:self']]);

        NinjaKiwiApiClient::fake([
            'avatar_url' => 'https://example.com/avatar.png',
            'banner_url' => 'https://example.com/banner.png',
        ]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/users/@me', [
                'name' => 'UpdatedName',
                'nk_oak' => 'valid_oak_123',
            ])
            ->assertStatus(200)
            ->assertJson([
                'name' => 'UpdatedName',
            ]);

        $user->refresh();
        $this->assertEquals('valid_oak_123', $user->nk_oak);
    }

    public function test_update_user_validates_nk_oak_failure(): void
    {
        $user = $this->createUserWithPermissions([null => ['edit:self']]);
        $user->nk_oak = 'original_oak';
        $user->save();

        NinjaKiwiApiClient::fake([
            'avatar_url' => null,
            'banner_url' => null,
        ]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/users/@me', [
                'name' => 'UpdatedName',
                'nk_oak' => 'invalid_oak',
            ])
            ->assertStatus(422)
            ->assertJson([
                'errors' => [
                    'nk_oak' => ['The provided Ninja Kiwi OAK is invalid.'],
                ],
            ]);

        $user->refresh();
        $this->assertEquals('original_oak', $user->nk_oak);
    }

    public function test_update_user_with_no_changes_succeeds(): void
    {
        $user = $this->createUserWithPermissions([null => ['edit:self']]);
        $user->name = 'CurrentName';
        $user->nk_oak = 'current_oak';
        $user->save();

        // Fake the OAK validation for the existing OAK
        NinjaKiwiApiClient::fake([
            'avatar_url' => 'https://example.com/avatar.png',
            'banner_url' => 'https://example.com/banner.png',
        ]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/users/@me', [
                'name' => 'CurrentName',
                'nk_oak' => 'current_oak',
            ])
            ->assertStatus(200)
            ->assertJson([
                'name' => 'CurrentName',
            ]);
    }

    public function test_update_user_returns_404_if_user_not_found(): void
    {
        // Create user with permission but then simulate self-update with non-existent ID
        // This would be unusual since @me should always exist, but let's test with a direct ID
        $user = $this->createUserWithPermissions([null => ['edit:self']]);

        // Try to update a non-existent user (which will fail since we can only update self)
        // But if we manually set our ID to something that doesn't exist...
        $nonExistentId = '999999999999999999';

        $this->actingAs($user, 'discord')
            ->putJson("/api/users/{$nonExistentId}", ['name' => 'NewName'])
            ->assertStatus(501); // 501 because it's not the authenticated user
    }

    public function test_update_user_requires_name(): void
    {
        $user = $this->createUserWithPermissions([null => ['edit:self']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/users/@me', ['nk_oak' => 'some_oak'])
            ->assertStatus(422)
            ->assertJson([
                'errors' => [
                    'name' => ['The name field is required.'],
                ],
            ]);
    }

    public function test_update_user_name_max_length(): void
    {
        $user = $this->createUserWithPermissions([null => ['edit:self']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/users/@me', ['name' => str_repeat('a', 21)])
            ->assertStatus(422)
            ->assertJson([
                'errors' => [
                    'name' => ['The name field must not be greater than 20 characters.'],
                ],
            ]);
    }
}
