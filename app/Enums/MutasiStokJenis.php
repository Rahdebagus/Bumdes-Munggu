<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum MutasiStokJenis: string
{
    use HasValues;

    case Opening = 'opening';
    case Pembelian = 'pembelian';
    case Penjualan = 'penjualan';
    case Opname = 'opname';
    case Reversal = 'reversal';
}
