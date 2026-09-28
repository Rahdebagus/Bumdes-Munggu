<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum TransaksiJenis: string
{
    use HasValues;

    case Pembelian = 'pembelian';
    case Penjualan = 'penjualan';
    case Pemasukan = 'pemasukan';
    case Pengeluaran = 'pengeluaran';
    case Pencairan = 'pencairan';
    case Angsuran = 'angsuran';
    case Penyusutan = 'penyusutan';
    case Penyesuaian = 'penyesuaian';
}
