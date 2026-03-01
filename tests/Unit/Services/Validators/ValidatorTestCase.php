<?php

namespace Tests\Unit\Services\Validators;

use App\Models\Format;
use Tests\TestCase;

class ValidatorTestCase extends TestCase
{
    protected function patchFormat(int $formatId, string $runStatus): Format
    {
        $format = Format::findOrFail($formatId);
        $format->run_submission_status = $runStatus;
        $format->save();
        return $format;
    }
}
