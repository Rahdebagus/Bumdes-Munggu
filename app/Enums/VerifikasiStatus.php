<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum VerifikasiStatus: string
{
    use HasValues;

    case BelumDiperiksa = 'belum_diperiksa';
    case Valid = 'valid';
    case Ditolak = 'ditolak';
}
