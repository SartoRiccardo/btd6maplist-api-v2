<?php

namespace Tests\Unit\Services\Validators;

use App\Constants\FormatConstants;
use App\Models\Map;
use App\Models\MapListMeta;
use App\Services\CompletionSubmission\CompletionSubmissionValidatorFactory;
use Illuminate\Validation\ValidationException;

class ExpertListValidatorTest extends ValidatorTestCase
{
    private CompletionSubmissionValidatorFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new CompletionSubmissionValidatorFactory();
    }

    public function test_expert_validator_fails_when_black_border_true_and_no_videos(): void
    {
        $format = $this->patchFormat(FormatConstants::EXPERT_LIST, 'open');
        $user = $this->createUserWithPermissions([$format->id => ['create:completion_submission']]);
        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create(['difficulty' => 2]);

        $validator = $this->factory->getValidator($format->id);

        $data = [
            'format_id' => $format->id,
            'map' => $map->code,
            'players' => ['123456789012345678'],
            'proof_videos' => [],
            'black_border' => true,
            'no_geraldo' => false,
            'lcc' => null,
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Video proof is required for this submission.');

        $validator->validate($data, $user);
    }

    public function test_expert_validator_fails_when_lcc_provided_and_no_videos(): void
    {
        $format = $this->patchFormat(FormatConstants::EXPERT_LIST, 'open');
        $user = $this->createUserWithPermissions([$format->id => ['create:completion_submission']]);
        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create(['difficulty' => 2]);

        $validator = $this->factory->getValidator($format->id);

        $data = [
            'format_id' => $format->id,
            'map' => $map->code,
            'players' => ['123456789012345678'],
            'proof_videos' => [],
            'black_border' => false,
            'no_geraldo' => false,
            'lcc' => ['leftover' => 1000],
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Video proof is required for this submission.');

        $validator->validate($data, $user);
    }

    public function test_expert_validator_succeeds_when_no_geraldo_true_on_difficulty_2_and_no_videos(): void
    {
        $format = $this->patchFormat(FormatConstants::EXPERT_LIST, 'open');
        $user = $this->createUserWithPermissions([$format->id => ['create:completion_submission']]);
        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create(['difficulty' => 2]); // Medium Expert - no video required

        $validator = $this->factory->getValidator($format->id);

        $data = [
            'format_id' => $format->id,
            'map' => $map->code,
            'players' => ['123456789012345678'],
            'proof_videos' => [],
            'black_border' => false,
            'no_geraldo' => true,
            'lcc' => null,
        ];

        $this->expectNotToPerformAssertions();

        $validator->validate($data, $user);
    }

    public function test_expert_validator_fails_when_no_geraldo_true_on_difficulty_3_and_no_videos(): void
    {
        $format = $this->patchFormat(FormatConstants::EXPERT_LIST, 'open');
        $user = $this->createUserWithPermissions([$format->id => ['create:completion_submission']]);
        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create(['difficulty' => 3]); // True Expert - video required

        $validator = $this->factory->getValidator($format->id);

        $data = [
            'format_id' => $format->id,
            'map' => $map->code,
            'players' => ['123456789012345678'],
            'proof_videos' => [],
            'black_border' => false,
            'no_geraldo' => true,
            'lcc' => null,
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Video proof is required for this submission.');

        $validator->validate($data, $user);
    }

    public function test_expert_validator_succeeds_when_no_geraldo_true_on_difficulty_4_with_videos(): void
    {
        $format = $this->patchFormat(FormatConstants::EXPERT_LIST, 'open');
        $user = $this->createUserWithPermissions([$format->id => ['create:completion_submission']]);
        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create(['difficulty' => 4]); // Extreme Expert

        $validator = $this->factory->getValidator($format->id);

        $data = [
            'format_id' => $format->id,
            'map' => $map->code,
            'players' => ['123456789012345678'],
            'proof_videos' => ['https://www.youtube.com/watch?v=test'],
            'black_border' => false,
            'no_geraldo' => true,
            'lcc' => null,
        ];

        $this->expectNotToPerformAssertions();

        $validator->validate($data, $user);
    }

    public function test_expert_validator_fails_when_no_geraldo_true_on_difficulty_4_and_no_videos(): void
    {
        $format = $this->patchFormat(FormatConstants::EXPERT_LIST, 'open');
        $user = $this->createUserWithPermissions([$format->id => ['create:completion_submission']]);
        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create(['difficulty' => 4]); // Extreme Expert - video required

        $validator = $this->factory->getValidator($format->id);

        $data = [
            'format_id' => $format->id,
            'map' => $map->code,
            'players' => ['123456789012345678'],
            'proof_videos' => [],
            'black_border' => false,
            'no_geraldo' => true,
            'lcc' => null,
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Video proof is required for this submission.');

        $validator->validate($data, $user);
    }
}
