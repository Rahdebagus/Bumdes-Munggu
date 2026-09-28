<?php

namespace App\Domain\Inventory;

use App\Domain\Shared\DomainException;
use App\Models\MutasiStok;
use App\Models\Produk;

/**
 * Penulis buku besar stok (mutasi_stok immutable). Menyediakan state terkini
 * (dari mutasi terakhir) dan menulis satu baris + memperbarui cache produk.
 * Nilai baris dihitung strategy terhadap produk yang sudah dikunci; ledger
 * menegakkan urutan monotonik dan larangan backdate.
 */
class StockLedger
{
    /**
     * @return array{qty: string, nilai: string, urutan: int, lastDate: ?string}
     */
    public function currentState(int $produkId): array
    {
        $last = MutasiStok::where('produk_id', $produkId)->orderByDesc('urutan')->first();

        return [
            'qty' => (string) ($last->saldo_kuantitas ?? '0'),
            'nilai' => (string) ($last->saldo_nilai ?? '0'),
            'urutan' => (int) ($last->urutan ?? 0),
            'lastDate' => $last?->tanggal?->toDateString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $intent
     */
    public function persist(Produk $produk, array $intent): MutasiStok
    {
        $last = MutasiStok::where('produk_id', $produk->id)->orderByDesc('urutan')->first();
        if ($last && $last->tanggal->toDateString() > $intent['tanggal']) {
            throw DomainException::stockBackdate(
                "Mutasi stok mundur sebelum mutasi terakhir produk {$produk->kode_produk} ditolak."
            );
        }

        $mutasi = MutasiStok::create([
            'produk_id' => $produk->id,
            'transaksi_id' => $intent['transaksi_id'] ?? null,
            'transaksi_barang_id' => $intent['transaksi_barang_id'] ?? null,
            'opening_rincian_id' => $intent['opening_rincian_id'] ?? null,
            'tanggal' => $intent['tanggal'],
            'urutan' => $intent['urutan'],
            'jenis' => $intent['jenis'],
            'kuantitas_masuk' => $intent['kuantitas_masuk'],
            'kuantitas_keluar' => $intent['kuantitas_keluar'],
            'nilai_masuk' => $intent['nilai_masuk'],
            'nilai_keluar' => $intent['nilai_keluar'],
            'harga_pokok' => $intent['harga_pokok'],
            'saldo_kuantitas' => $intent['saldo_kuantitas'],
            'saldo_nilai' => $intent['saldo_nilai'],
            'source_key' => $intent['source_key'],
        ]);

        $produk->update([
            'stok_terkini' => $intent['saldo_kuantitas'],
            'harga_pokok_rata' => $intent['harga_pokok_rata'],
            'stock_version' => $produk->stock_version + 1,
        ]);

        return $mutasi;
    }
}
