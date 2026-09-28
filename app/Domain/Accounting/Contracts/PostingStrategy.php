<?php

namespace App\Domain\Accounting\Contracts;

use App\Models\Transaksi;

/**
 * Strategy posting per jenis transaksi. Semua perhitungan memakai data server
 * dan dijalankan di dalam DB transaction PostingService (setelah lock periode +
 * sumber). Strategy mengunci sendiri produk/target yang dipengaruhinya.
 *
 * - plan(): rencana jurnal (dihitung ulang saat post).
 * - documentTotal(): total dokumen komersial (mis. total penjualan), TERPISAH
 *   dari jumlah debit jurnal yang bisa memuat HPP.
 * - applyEffects(): efek domain (mutasi_stok, alokasi subledger, cache) —
 *   dijalankan setelah baris jurnal ditulis, dalam transaksi yang sama.
 */
interface PostingStrategy extends JournalPlanner
{
    public function documentTotal(Transaksi $trx): string;

    public function applyEffects(Transaksi $trx): void;
}
