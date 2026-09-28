<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum AngsuranStatus: string
{
    use HasValues;

    case BelumDibayar = 'belum_dibayar';
    case Sebagian = 'sebagian';
    case Lunas = 'lunas';
    case Terlambat = 'terlambat';
}
