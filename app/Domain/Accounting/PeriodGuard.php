<?php

namespace App\Domain\Accounting;

use App\Domain\Shared\DomainException;
use App\Enums\PeriodeStatus;
use App\Models\PeriodeAkuntansi;

/**
 * Menjaga posting hanya ke periode terbuka dengan saldo awal (cutover) terkunci
 * (dokumen06 pseudocode; dokumen08 §8). Relaksasi carry-forward multi-periode
 * = tahap09.
 */
class PeriodGuard
{
    public function assertPostable(PeriodeAkuntansi $periode): void
    {
        if ($periode->status !== PeriodeStatus::Terbuka) {
            throw DomainException::periodClosed("Periode {$periode->bulan}/{$periode->tahun} tertutup; posting ditolak.");
        }

        if ($periode->opening_locked_at === null) {
            throw DomainException::openingNotLocked("Saldo awal periode {$periode->bulan}/{$periode->tahun} belum dikunci.");
        }
    }
}
