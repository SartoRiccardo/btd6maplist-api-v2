<?php

namespace Tests\Unit\Services\Validators;

use App\Constants\FormatConstants;
use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\User;
use App\Services\CompletionSubmission\CompletionSubmissionValidatorFactory;
use Illuminate\Validation\ValidationException;

class BaseValidatorTest extends ValidatorTestCase
{
    private CompletionSubmissionValidatorFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new CompletionSubmissionValidatorFactory();
    }

    public function test_base_validator_fails_when_format_is_closed(): void
    {
        $format = $this->patchFormat(FormatConstants::BEST_OF_THE_BEST, 'closed');
        $user = $this->createUserWithPermissions([$format->id => ['create:completion_submission']]);
        $map = Map::factory()->create();
        MapListMeta::factory()->empty()->for($map)->create(['botb_difficulty' => 1]);

        $validator = $this->factory->getValidator($format->id);

        $data = [
            'format_id' => $format->id,
            'map' => $map->code,
            'players' => ['123456789012345678'],
            'proof_videos' => [],
            'black_border' => false,
            'no_geraldo' => false,
            'lcc' => null,
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Submissions are closed for this format.');

        $validator->validate($data, $user);
    }

    public function test_base_validator_fails_when_lcc_only_format_without_lcc(): void
    {
        $format = $this->patchFormat(FormatConstants::BEST_OF_THE_BEST, 'lcc_only');
        $user = $this->createUserWithPermissions([$format->id => ['create:completion_submission']]);
        $map = Map::factory()->create();
        MapListMeta::factory()->empty()->for($map)->create(['botb_difficulty' => 1]);

        $validator = $this->factory->getValidator($format->id);

        $data = [
            'format_id' => $format->id,
            'map' => $map->code,
            'players' => ['123456789012345678'],
            'proof_videos' => [],
            'black_border' => false,
            'no_geraldo' => false,
            'lcc' => null,
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('This format requires Least Cost Chimps data to be provided.');

        $validator->validate($data, $user);
    }

    public function test_base_validator_fails_when_user_lacks_permission(): void
    {
        $format = $this->patchFormat(FormatConstants::BEST_OF_THE_BEST, 'open');
        $user = User::factory()->create(); // No permissions
        $map = Map::factory()->create();
        MapListMeta::factory()->empty()->for($map)->create(['botb_difficulty' => 1]);

        $validator = $this->factory->getValidator($format->id);

        $data = [
            'format_id' => $format->id,
            'map' => $map->code,
            'players' => ['123456789012345678'],
            'proof_videos' => [],
            'black_border' => false,
            'no_geraldo' => false,
            'lcc' => null,
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('You do not have permission to submit completions for this format.');

        $validator->validate($data, $user);
    }

    public function test_base_validator_fails_when_user_has_recording_requirement_and_no_videos(): void
    {
        $format = $this->patchFormat(FormatConstants::BEST_OF_THE_BEST, 'open');
        $user = $this->createUserWithPermissions([
            $format->id => ['create:completion_submission', 'require:completion_submission:recording']
        ]);
        $map = Map::factory()->create();
        MapListMeta::factory()->empty()->for($map)->create(['botb_difficulty' => 1]);

        $validator = $this->factory->getValidator($format->id);

        $data = [
            'format_id' => $format->id,
            'map' => $map->code,
            'players' => ['123456789012345678'],
            'proof_videos' => [],
            'black_border' => false,
            'no_geraldo' => false,
            'lcc' => null,
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Video proof is required for your submission.');

        $validator->validate($data, $user);
    }

    public function test_base_validator_fails_when_map_not_valid_for_format(): void
    {
        $format = $this->patchFormat(FormatConstants::BEST_OF_THE_BEST, 'open');
        $user = $this->createUserWithPermissions([$format->id => ['create:completion_submission']]);
        $map = Map::factory()->create();
        MapListMeta::factory()->empty()->for($map)->create(['botb_difficulty' => null]);

        $validator = $this->factory->getValidator($format->id);

        $data = [
            'format_id' => $format->id,
            'map' => $map->code,
            'players' => ['123456789012345678'],
            'proof_videos' => [],
            'black_border' => false,
            'no_geraldo' => false,
            'lcc' => null,
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('This map is not valid for submission to the specified format.');

        $validator->validate($data, $user);
    }

    public function test_base_validator_succeeds_with_valid_data(): void
    {
        $format = $this->patchFormat(FormatConstants::BEST_OF_THE_BEST, 'open');
        $user = $this->createUserWithPermissions([$format->id => ['create:completion_submission']]);
        $map = Map::factory()->create();
        MapListMeta::factory()->empty()->for($map)->create(['botb_difficulty' => 1]);

        $validator = $this->factory->getValidator($format->id);

        $data = [
            'format_id' => $format->id,
            'map' => $map->code,
            'players' => ['123456789012345678'],
            'proof_videos' => [],
            'black_border' => false,
            'no_geraldo' => false,
            'lcc' => null,
        ];

        $this->expectNotToPerformAssertions();

        $validator->validate($data, $user);
    }
}
