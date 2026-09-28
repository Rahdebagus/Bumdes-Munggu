<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum PihakJenis: string
{
    use HasValues;

    case Pelanggan = 'pelanggan';
    case Pemasok = 'pemasok';
    case Peminjam = 'peminjam';
}
