<?php

namespace Tests\Unit\Services\Validators;

use App\Constants\FormatConstants;
use App\Models\Config;
use App\Models\Map;
use App\Models\MapListMeta;
use App\Services\CompletionSubmission\CompletionSubmissionValidatorFactory;
use Illuminate\Validation\ValidationException;

class MaplistValidatorTest extends BaseValidatorTest
{
    private CompletionSubmissionValidatorFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new CompletionSubmissionValidatorFactory();

        // Set up map_count config
        Config::factory()->name('map_count')->create(['value' => 50]);
    }

    public function test_maplist_validator_fails_when_placement_curver_exceeds_map_count(): void
    {
        $format = $this->patchFormat(FormatConstants::MAPLIST, 'open');
        $user = $this->createUserWithPermissions([$format->id => ['create:completion_submission']]);
        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create(['placement_curver' => 51]); // Exceeds map_count of 50

        $validator = $this->factory->getValidator($format->id);

        $data = [
            'format_id' => $format->id,
            'map' => $map->code,
            'players' => [$user->discord_id],
            'proof_videos' => [],
            'black_border' => false,
            'no_geraldo' => false,
            'lcc' => null,
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Map placement is not within the valid range');

        $validator->validate($data, $user);
    }

    public function test_maplist_validator_fails_when_placement_allver_exceeds_map_count(): void
    {
        $format = $this->patchFormat(FormatConstants::MAPLIST_ALL_VERSIONS, 'open');
        $user = $this->createUserWithPermissions([$format->id => ['create:completion_submission']]);
        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create(['placement_allver' => 51]); // Exceeds map_count of 50

        $validator = $this->factory->getValidator($format->id);

        $data = [
            'format_id' => $format->id,
            'map' => $map->code,
            'players' => [$user->discord_id],
            'proof_videos' => [],
            'black_border' => false,
            'no_geraldo' => false,
            'lcc' => null,
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Map placement is not within the valid range');

        $validator->validate($data, $user);
    }

    public function test_maplist_validator_fails_when_black_border_true_and_no_videos(): void
    {
        $format = $this->patchFormat(FormatConstants::MAPLIST, 'open');
        $user = $this->createUserWithPermissions([$format->id => ['create:completion_submission']]);
        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create(['placement_curver' => 1]);

        $validator = $this->factory->getValidator($format->id);

        $data = [
            'format_id' => $format->id,
            'map' => $map->code,
            'players' => [$user->discord_id],
            'proof_videos' => [],
            'black_border' => true,
            'no_geraldo' => false,
            'lcc' => null,
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Video proof is required for this submission.');

        $validator->validate($data, $user);
    }

    public function test_maplist_validator_fails_when_no_geraldo_true_and_no_videos(): void
    {
        $format = $this->patchFormat(FormatConstants::MAPLIST_ALL_VERSIONS, 'open');
        $user = $this->createUserWithPermissions([$format->id => ['create:completion_submission']]);
        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create(['placement_allver' => 1]);

        $validator = $this->factory->getValidator($format->id);

        $data = [
            'format_id' => $format->id,
            'map' => $map->code,
            'players' => [$user->discord_id],
            'proof_videos' => [],
            'black_border' => false,
            'no_geraldo' => true,
            'lcc' => null,
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Video proof is required for this submission.');

        $validator->validate($data, $user);
    }

    public function test_maplist_validator_succeeds_when_placement_is_valid(): void
    {
        $format = $this->patchFormat(FormatConstants::MAPLIST, 'open');
        $user = $this->createUserWithPermissions([$format->id => ['create:completion_submission']]);
        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create(['placement_curver' => 1]);

        $validator = $this->factory->getValidator($format->id);

        $data = [
            'format_id' => $format->id,
            'map' => $map->code,
            'players' => [$user->discord_id],
            'proof_videos' => [],
            'black_border' => false,
            'no_geraldo' => false,
            'lcc' => null,
        ];

        $this->expectNotToPerformAssertions();

        $validator->validate($data, $user);
    }
}
