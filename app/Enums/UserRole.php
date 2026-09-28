<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Pimpinan = 'pimpinan';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Pimpinan => 'Pimpinan',
        };
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_map(fn (self $r) => $r->value, self::cases());
    }
}
