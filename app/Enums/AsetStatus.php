<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum AsetStatus: string
{
    use HasValues;

    case Aktif = 'aktif';
    case Dilepas = 'dilepas';
    case Dihapus = 'dihapus';
}
