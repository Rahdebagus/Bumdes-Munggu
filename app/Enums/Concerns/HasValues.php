<?php

namespace App\Enums\Concerns;

trait HasValues
{
    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    /** Aturan validasi Laravel: in:a,b,c */
    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }
}
