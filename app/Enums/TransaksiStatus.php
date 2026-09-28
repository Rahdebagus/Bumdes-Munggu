<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum TransaksiStatus: string
{
    use HasValues;

    case Draft = 'draft';
    case Posted = 'posted';
    case Reversed = 'reversed';
}
