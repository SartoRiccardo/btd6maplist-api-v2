<?php

namespace Tests\Feature\Config;

use App\Constants\FormatConstants;
use App\Models\Config;
use Tests\TestCase;
use Tests\Traits\TestsDiscordAuthMiddleware;
use PHPUnit\Metadata\Group;

class UpdateConfigTest extends TestCase
{
    use TestsDiscordAuthMiddleware;

    protected function setUp(): void
    {
        parent::setUp();
        Config::query()->delete();
        // Create a test config for auth middleware tests
        Config::factory()->create(['name' => 'test', 'value' => '100', 'type' => 'int']);
    }

    protected function endpoint(): string
    {
        return '/api/config';
    }

    protected function method(): string
    {
        return 'PUT';
    }

    protected function requestData(): array
    {
        return ['test' => 200];
    }

    #[Group('config')]
    public function test_update_config_with_non_existent_key_returns_422(): void
    {
        $user = $this->createUserWithPermissions([null => ['edit:config']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/config', ['nonexistent_key' => 100])
            ->assertStatus(422)
            ->assertJsonPath('errors.nonexistent_key', ['Invalid configuration key or insufficient permissions.']);
    }

    #[Group('config')]
    public function test_update_config_without_permission_returns_422(): void
    {
        Config::factory()->forFormats([FormatConstants::EXPERT_LIST])->create([
            'name' => 'expert_only',
            'value' => '100',
            'type' => 'int',
        ]);

        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:config']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/config', ['expert_only' => 200])
            ->assertStatus(422)
            ->assertJsonPath('errors.expert_only', ['Invalid configuration key or insufficient permissions.']);
    }

    #[Group('config')]
    public function test_update_config_with_wrong_value_type_returns_422(): void
    {
        Config::factory()->create(['name' => 'int_only', 'value' => '100', 'type' => 'int']);
        $user = $this->createUserWithPermissions([null => ['edit:config']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/config', ['int_only' => 'not an int'])
            ->assertStatus(422)
            ->assertJsonPath('errors.int_only', ["Value for 'int_only' must be int."]);
    }

    #[Group('config')]
    public function test_update_config_is_atomic_on_failure(): void
    {
        Config::factory()->create(['name' => 'valid_key', 'value' => '100', 'type' => 'int']);
        Config::factory()->create(['name' => 'invalid_key', 'value' => '200', 'type' => 'int']);

        $user = $this->createUserWithPermissions([null => ['edit:config']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/config', [
                'valid_key' => 999,
                'invalid_key' => 'wrong type'
            ])
            ->assertStatus(422);

        // Verify valid_key was NOT updated via GET
        $this->actingAs($user, 'discord')
            ->getJson('/api/config')
            ->assertJsonPath('valid_key', 100);
    }

    #[Group('config')]
    public function test_update_config_success_with_global_permission(): void
    {
        Config::factory()->create(['name' => 'global_config', 'value' => '100', 'type' => 'int']);
        $user = $this->createUserWithPermissions([null => ['edit:config']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/config', ['global_config' => 200])
            ->assertStatus(200)
            ->assertJsonPath('global_config', 200);

        // Verify update persisted via GET
        $this->getJson('/api/config')
            ->assertJsonPath('global_config', 200);
    }

    #[Group('config')]
    public function test_global_admin_can_modify_scoped_config(): void
    {
        Config::factory()->forFormats([FormatConstants::MAPLIST])->create([
            'name' => 'scoped_config',
            'value' => '100',
            'type' => 'int',
        ]);

        $user = $this->createUserWithPermissions([null => ['edit:config']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/config', ['scoped_config' => 200])
            ->assertStatus(200)
            ->assertJsonPath('scoped_config', 200);

        // Verify update persisted via GET
        $this->getJson('/api/config')
            ->assertJsonPath('scoped_config', 200);
    }

    #[Group('config')]
    public function test_update_config_success_with_format_permission(): void
    {
        Config::factory()->forFormats([FormatConstants::MAPLIST])->create([
            'name' => 'maplist_config',
            'value' => '100',
            'type' => 'int',
        ]);

        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:config']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/config', ['maplist_config' => 200])
            ->assertStatus(200)
            ->assertJsonPath('maplist_config', 200);
    }

    #[Group('config')]
    public function test_update_global_config_requires_global_permission(): void
    {
        Config::factory()->create(['name' => 'global_config', 'value' => '100', 'type' => 'int']);

        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:config']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/config', ['global_config' => 200])
            ->assertStatus(422)
            ->assertJsonPath('errors.global_config', ['Invalid configuration key or insufficient permissions.']);
    }

    #[Group('config')]
    public function test_update_config_returns_full_updated_object(): void
    {
        Config::factory()->create(['name' => 'config1', 'value' => '100', 'type' => 'int']);
        Config::factory()->create(['name' => 'config2', 'value' => '1.5', 'type' => 'float']);

        $user = $this->createUserWithPermissions([null => ['edit:config']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/config', ['config1' => 200])
            ->assertStatus(200)
            ->assertJson([
                'config1' => 200,
                'config2' => 1.5,
            ]);
    }
}
