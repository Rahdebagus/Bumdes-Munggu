<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum PinjamanStatus: string
{
    use HasValues;

    case Draft = 'draft';
    case Aktif = 'aktif';
    case Lunas = 'lunas';
    case Macet = 'macet';
    case Dibatalkan = 'dibatalkan';
}
