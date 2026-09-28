<?php

namespace App\Enums;

enum UserStatus: string
{
    case Aktif = 'aktif';
    case Nonaktif = 'nonaktif';

    public function label(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif',
            self::Nonaktif => 'Nonaktif',
        };
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
