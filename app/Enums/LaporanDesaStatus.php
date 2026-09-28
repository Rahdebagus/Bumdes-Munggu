<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum LaporanDesaStatus: string
{
    use HasValues;

    case Draft = 'draft';
    case Diajukan = 'diajukan';
    case Disetujui = 'disetujui';
    case Dikirim = 'dikirim';
    case Diterima = 'diterima';
    case Revisi = 'revisi';
}
