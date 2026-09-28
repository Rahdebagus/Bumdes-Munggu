<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum UnitJenisUsaha: string
{
    use HasValues;

    case Dagang = 'dagang';
    case Kredit = 'kredit';
}
