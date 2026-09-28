<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum StatusUmum: string
{
    use HasValues;

    case Aktif = 'aktif';
    case Nonaktif = 'nonaktif';
}
