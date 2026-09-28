<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum SaldoRincianJenis: string
{
    use HasValues;

    case Stok = 'stok';
    case PiutangUsaha = 'piutang_usaha';
    case UtangUsaha = 'utang_usaha';
    case Pinjaman = 'pinjaman';
    case Aset = 'aset';
    case AkumulasiAset = 'akumulasi_aset';
}
