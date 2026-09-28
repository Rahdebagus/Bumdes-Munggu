<?php

namespace App\Domain\Accounting;

use App\Domain\Shared\Decimal;
use App\Models\TransaksiDetail;

/**
 * Buku besar bersumber transaksi_detail terposting. Menyertakan original
 * reversed pada tanggalnya (filter `posted_at IS NOT NULL`, bukan status),
 * beserta pembaliknya pada tanggal pembalik (dokumen06 §Reverse, dokumen08 §9).
 */
class LedgerQuery
{
    /** Saldo (debit - kredit) sebuah akun untuk transaksi terposting s/d cutoff. */
    public function accountBalance(int $akunId, ?string $cutoffDate = null): string
    {
        $base = TransaksiDetail::where('akun_id', $akunId)
            ->whereHas('transaksi', function ($t) use ($cutoffDate) {
                $t->whereNotNull('posted_at');
                if ($cutoffDate !== null) {
                    $t->whereDate('tanggal', '<=', $cutoffDate);
                }
            });

        $debit = (string) (clone $base)->sum('debit');
        $kredit = (string) (clone $base)->sum('kredit');

        return Decimal::sub($debit, $kredit);
    }
}
