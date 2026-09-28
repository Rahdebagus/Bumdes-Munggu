<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum BasisBunga: string
{
    use HasValues;

    case FlatTotal = 'flat_total';
    case FlatBulanan = 'flat_bulanan';
}
