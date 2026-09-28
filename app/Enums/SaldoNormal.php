<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum SaldoNormal: string
{
    use HasValues;

    case Debit = 'debit';
    case Kredit = 'kredit';
}
