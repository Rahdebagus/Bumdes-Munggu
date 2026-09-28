<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum PeriodeStatus: string
{
    use HasValues;

    case Terbuka = 'terbuka';
    case Ditutup = 'ditutup';
}
