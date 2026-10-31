<?php

namespace App\Domain\Inventory;

use App\Models\MutasiStok;
use App\Models\Produk;
use Illuminate\Support\Collection;

/**
 * Saldo kartu stok semua produk pada suatu tanggal dalam satu query: mutasi
 * terakhir (urutan tertinggi) per produk. Urutan monoton terhadap tanggal
 * karena backdate ditolak StockLedger, dan UQ(produk_id, urutan) menjamin
 * satu baris per produk.
 */
class StockSnapshot
{
    /**
     * @param  bool  $inklusif  true = mutasi s/d tanggal (≤), false = sebelum tanggal (<)
     * @return Collection<int, MutasiStok> diindeks produk_id
     */
    public function terakhir(string $tanggal, bool $inklusif = true, ?int $unitId = null): Collection
    {
        $akhir = MutasiStok::query()
            ->whereDate('tanggal', $inklusif ? '<=' : '<', $tanggal)
            ->when($unitId, fn ($q) => $q->whereIn('produk_id', Produk::where('unit_usaha_id', $unitId)->select('id')))
            ->groupBy('produk_id')
            ->selectRaw('produk_id, MAX(urutan) as urutan');

        return MutasiStok::query()
            ->joinSub($akhir, 'akhir', fn ($j) => $j->on('mutasi_stok.produk_id', '=', 'akhir.produk_id')
                ->on('mutasi_stok.urutan', '=', 'akhir.urutan'))
            ->select('mutasi_stok.*')
            ->get()
            ->keyBy('produk_id');
    }
}
