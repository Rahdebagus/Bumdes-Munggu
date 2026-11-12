<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum PihakJenis: string
{
    use HasValues;

    case Pelanggan = 'pelanggan';
    case Pemasok = 'pemasok';
    case Peminjam = 'peminjam';

    /** Awalan kode pihak otomatis, mis. PLG-001. */
    public function prefix(): string
    {
        return match ($this) {
            self::Pelanggan => 'PLG',
            self::Pemasok => 'PMS',
            self::Peminjam => 'PMJ',
        };
    }
}
