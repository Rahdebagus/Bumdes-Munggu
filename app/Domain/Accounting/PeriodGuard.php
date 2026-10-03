<?php

namespace App\Domain\Accounting;

use App\Domain\Shared\DomainException;
use App\Enums\PeriodeStatus;
use App\Models\PeriodeAkuntansi;

/**
 * Menjaga posting hanya ke periode terbuka dengan saldo awal (cutover) terkunci
 * (dokumen06 pseudocode; dokumen08 §8). Relaksasi carry-forward (tahap09):
 * setelah periode cutover terkunci, seluruh periode terbuka berikutnya ikut
 * dapat diposting tanpa harus mengunci opening sendiri (saldo berjalan dibawa
 * maju lewat jurnal, bukan opening baru).
 */
class PeriodGuard
{
    public function assertPostable(PeriodeAkuntansi $periode): void
    {
        if ($periode->status !== PeriodeStatus::Terbuka) {
            throw DomainException::periodClosed("Periode {$periode->bulan}/{$periode->tahun} tertutup; posting ditolak.");
        }

        // Opening periode ini terkunci → boleh. Jika tidak, boleh asalkan ada
        // periode cutover lebih awal yang openingnya sudah terkunci.
        if ($periode->opening_locked_at === null && ! $this->earlierCutoverLocked($periode)) {
            throw DomainException::openingNotLocked("Saldo awal periode {$periode->bulan}/{$periode->tahun} belum dikunci (dan belum ada cutover terkunci sebelumnya).");
        }
    }

    private function earlierCutoverLocked(PeriodeAkuntansi $periode): bool
    {
        return PeriodeAkuntansi::whereNotNull('opening_locked_at')
            ->whereDate('tanggal_mulai', '<', $periode->tanggal_mulai->toDateString())
            ->exists();
    }
}
