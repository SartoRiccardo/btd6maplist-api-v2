<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class MapSubmissionStatusCast implements CastsAttributes
{
    private const STATUS_MAP = [
        0 => 'closed',
        1 => 'open',
    ];

    public function get($model, string $key, $value, array $attributes): string
    {
        return self::STATUS_MAP[$value] ?? 'closed';
    }

    public function set($model, string $key, $value, array $attributes): int
    {
        if (is_int($value)) {
            return $value;
        }

        $flipped = array_flip(self::STATUS_MAP);
        return $flipped[$value] ?? 0;
    }
}
