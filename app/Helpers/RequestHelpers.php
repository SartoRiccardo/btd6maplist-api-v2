<?php

namespace App\Helpers;

use Illuminate\Http\Request;

class RequestHelpers
{
    /**
     * Merge uploaded files and string inputs for a given field name into one ordered list.
     * Files and strings may appear at the same indices; files take precedence on collision.
     */
    public static function mergeFileAndUrlInputs(Request $request, string $field): array
    {
        $files  = $request->file($field) ?? [];
        $inputs = $request->input($field) ?? [];
        $merged = $files + $inputs;
        ksort($merged);
        return array_values($merged);
    }
}
