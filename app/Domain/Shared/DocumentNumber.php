<?php

namespace App\Domain\Shared;

use App\Models\Transaksi;
use Illuminate\Support\Carbon;

/**
 * Nomor nota berurutan per awalan & bulan: SL-202610-0001 (penjualan),
 * PB-… (pembelian), KS-… (kas), SA-… (stok awal). Dipanggil DI DALAM
 * DB::transaction; baris terakhir dikunci (lockForUpdate) agar dua penyimpanan
 * serentak tidak mendapat nomor sama (unique nomor_transaksi tetap penjaga akhir).
 */
class DocumentNumber
{
    public static function next(string $prefix, string $tanggal): string
    {
        $stem = $prefix.'-'.Carbon::parse($tanggal)->format('Ym').'-';

        $last = Transaksi::where('nomor_transaksi', 'like', $stem.'%')
            ->orderByDesc('nomor_transaksi')
            ->lockForUpdate()
            ->value('nomor_transaksi');

        $seq = $last ? ((int) substr($last, strlen($stem))) + 1 : 1;

        return $stem.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
