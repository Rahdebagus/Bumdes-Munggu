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

    /** Sudah disetujui Pimpinan (angka beku). */
    public function isFinal(): bool
    {
        return in_array($this, [self::Disetujui, self::Dikirim, self::Diterima], true);
    }
}
