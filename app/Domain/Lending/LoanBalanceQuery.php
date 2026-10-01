<?php

namespace App\Domain\Lending;

use App\Domain\Shared\Decimal;
use App\Models\Angsuran;
use App\Models\PembayaranAngsuran;

/**
 * Sumber kebenaran pembayaran pinjaman: histori pembayaran_angsuran dari
 * transaksi angsuran yang terposting & belum dibalik. Cache di angsuran/pinjaman
 * direkonsiliasi dari sini (dokumen05).
 */
class LoanBalanceQuery
{
    /**
     * @return array{pokok: string, bunga: string, denda: string}
     */
    public function paid(Angsuran $angsuran): array
    {
        $rows = PembayaranAngsuran::where('angsuran_id', $angsuran->id)
            ->whereHas('transaksi', fn ($q) => $q->whereNotNull('posted_at')->where('status', 'posted'))
            ->get();

        return [
            'pokok' => Decimal::of((string) $rows->sum('pokok')),
            'bunga' => Decimal::of((string) $rows->sum('bunga')),
            'denda' => Decimal::of((string) $rows->sum('denda')),
        ];
    }
}
