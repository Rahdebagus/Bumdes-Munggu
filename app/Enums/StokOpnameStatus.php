<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum StokOpnameStatus: string
{
    use HasValues;

    case Draft = 'draft';
    case Selesai = 'selesai';
    case MenungguPersetujuan = 'menunggu_persetujuan';
    case Disetujui = 'disetujui';
    case Posted = 'posted';
    case Dibatalkan = 'dibatalkan';
}
