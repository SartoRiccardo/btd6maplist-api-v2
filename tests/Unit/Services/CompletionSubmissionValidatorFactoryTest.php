<?php

namespace Tests\Unit\Services;

use App\Constants\FormatConstants;
use App\Services\CompletionSubmission\CompletionSubmissionValidatorFactory;
use App\Services\CompletionSubmission\Validators\BaseSubmissionValidator;
use App\Services\CompletionSubmission\Validators\ExpertListValidator;
use App\Services\CompletionSubmission\Validators\MaplistValidator;
use App\Services\CompletionSubmission\SubmissionValidatorInterface;
use Tests\TestCase;

class CompletionSubmissionValidatorFactoryTest extends TestCase
{
    private CompletionSubmissionValidatorFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new CompletionSubmissionValidatorFactory();
    }

    public function test_factory_returns_maplist_validator_for_format_1(): void
    {
        $validator = $this->factory->getValidator(FormatConstants::MAPLIST);

        $this->assertInstanceOf(MaplistValidator::class, $validator);
        $this->assertInstanceOf(SubmissionValidatorInterface::class, $validator);
    }

    public function test_factory_returns_maplist_validator_for_format_2(): void
    {
        $validator = $this->factory->getValidator(FormatConstants::MAPLIST_ALL_VERSIONS);

        $this->assertInstanceOf(MaplistValidator::class, $validator);
        $this->assertInstanceOf(SubmissionValidatorInterface::class, $validator);
    }

    public function test_factory_returns_expert_list_validator_for_format_51(): void
    {
        $validator = $this->factory->getValidator(FormatConstants::EXPERT_LIST);

        $this->assertInstanceOf(ExpertListValidator::class, $validator);
        $this->assertInstanceOf(SubmissionValidatorInterface::class, $validator);
    }

    public function test_factory_returns_base_validator_for_format_11(): void
    {
        $validator = $this->factory->getValidator(FormatConstants::NOSTALGIA_PACK);

        $this->assertInstanceOf(BaseSubmissionValidator::class, $validator);
        $this->assertInstanceOf(SubmissionValidatorInterface::class, $validator);
    }

    public function test_factory_returns_base_validator_for_unknown_format(): void
    {
        $validator = $this->factory->getValidator(999);

        $this->assertInstanceOf(BaseSubmissionValidator::class, $validator);
        $this->assertInstanceOf(SubmissionValidatorInterface::class, $validator);
    }
}
