<?php

namespace App\Domain\Accounting\Planners;

use App\Domain\Accounting\Contracts\JournalPlanner;
use App\Domain\Accounting\JournalLine;
use App\Domain\Accounting\JournalPlan;
use App\Models\Transaksi;

/**
 * Planner untuk jurnal penyesuaian (manual): rencana = baris draft yang
 * dimasukkan pengguna. Tidak ada perhitungan dari sumber lain — baris itulah
 * jurnalnya. Divalidasi seimbang saat post.
 */
class ManualJournalPlanner implements JournalPlanner
{
    public function plan(Transaksi $trx): JournalPlan
    {
        $lines = $trx->details()
            ->orderBy('nomor_baris')
            ->get()
            ->map(fn ($d) => new JournalLine(
                akunId: $d->akun_id,
                debit: $d->debit,
                kredit: $d->kredit,
                kelompokArusKas: $d->kelompok_arus_kas,
                keterangan: $d->keterangan,
            ))
            ->all();

        return new JournalPlan(...$lines);
    }
}
