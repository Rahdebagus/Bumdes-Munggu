<?php

namespace App\Domain\Inventory;

use App\Domain\Shared\Decimal;
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
    public function __construct(private MovingAverageCost $avg) {}

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
            'reversal_of_id' => $intent['reversal_of_id'] ?? null,
            'source_key' => $intent['source_key'],
        ]);

        $produk->update([
            'stok_terkini' => $intent['saldo_kuantitas'],
            'harga_pokok_rata' => $intent['harga_pokok_rata'],
            'stock_version' => $produk->stock_version + 1,
        ]);

        return $mutasi;
    }

    /**
     * Menulis baris pembalik untuk sebuah mutasi: arah dibalik, nilai sama,
     * saldo dihitung ulang terhadap state terkini. Produk harus sudah dikunci
     * dan mutasi asli harus yang terakhir (dicek pemanggil).
     */
    public function reverseMovement(MutasiStok $original, Produk $produk, int $reversalTrxId, string $tanggal): MutasiStok
    {
        $state = $this->currentState($produk->id);
        $originalMasuk = Decimal::isPositive((string) $original->kuantitas_masuk, Decimal::QTY);

        if ($originalMasuk) {
            // Mutasi asli MASUK → pembalik KELUAR.
            $qty = (string) $original->kuantitas_masuk;
            $nilai = (string) $original->nilai_masuk;
            $saldoQty = Decimal::sub($state['qty'], $qty, Decimal::QTY);
            $saldoNilai = Decimal::sub($state['nilai'], $nilai);
            $arah = ['kuantitas_masuk' => Decimal::of(0, Decimal::QTY), 'kuantitas_keluar' => $qty, 'nilai_masuk' => '0.00', 'nilai_keluar' => $nilai];
        } else {
            // Mutasi asli KELUAR → pembalik MASUK.
            $qty = (string) $original->kuantitas_keluar;
            $nilai = (string) $original->nilai_keluar;
            $saldoQty = Decimal::add($state['qty'], $qty, Decimal::QTY);
            $saldoNilai = Decimal::add($state['nilai'], $nilai);
            $arah = ['kuantitas_masuk' => $qty, 'kuantitas_keluar' => Decimal::of(0, Decimal::QTY), 'nilai_masuk' => $nilai, 'nilai_keluar' => '0.00'];
        }

        $intent = array_merge($arah, [
            'produk_id' => $produk->id,
            'transaksi_id' => $reversalTrxId,
            'opening_rincian_id' => null,
            'tanggal' => $tanggal,
            'urutan' => $state['urutan'] + 1,
            'jenis' => 'reversal',
            'harga_pokok' => (string) $original->harga_pokok,
            'saldo_kuantitas' => $saldoQty,
            'saldo_nilai' => $saldoNilai,
            'harga_pokok_rata' => $this->avg->average($saldoNilai, $saldoQty),
            'reversal_of_id' => $original->id,
            'source_key' => 'REV:'.$original->source_key,
        ]);

        return $this->persist($produk, $intent);
    }
}
