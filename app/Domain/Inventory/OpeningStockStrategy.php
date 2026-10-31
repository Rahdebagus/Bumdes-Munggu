<?php

namespace App\Domain\Inventory;

use App\Domain\Accounting\Contracts\PostingStrategy;
use App\Domain\Accounting\JournalLine;
use App\Domain\Accounting\JournalPlan;
use App\Domain\Accounting\SystemAccounts;
use App\Domain\Shared\Decimal;
use App\Domain\Shared\DomainException;
use App\Models\Produk;
use App\Models\Transaksi;
use App\Models\TransaksiBarang;

/**
 * Stok awal produk (sumber_tipe 'stok_awal', jenis penyesuaian). Tiap baris
 * barang = kuantitas awal × harga pokok. Dr Persediaan; Cr akun lawan
 * (transaksi.akun_lawan_id, default Modal). Menulis mutasi_stok jenis
 * 'opening' sehingga subledger stok = akun persediaan. Hanya untuk produk yang
 * belum punya mutasi (stok awal tidak boleh ditumpuk di atas histori).
 */
class OpeningStockStrategy implements PostingStrategy
{
    /** @var array<int, array<string, mixed>> */
    private array $memo = [];

    public function __construct(private StockLedger $ledger, private MovingAverageCost $avg) {}

    public function plan(Transaksi $trx): JournalPlan
    {
        return $this->compute($trx)['journal'];
    }

    public function documentTotal(Transaksi $trx): string
    {
        return $this->compute($trx)['total'];
    }

    public function applyEffects(Transaksi $trx): void
    {
        $c = $this->compute($trx);

        foreach ($c['intents'] as $intent) {
            $this->ledger->persist($c['produk'][$intent['produk_id']], $intent);
        }

        foreach ($c['barangUpdate'] as $barangId => $vals) {
            TransaksiBarang::whereKey($barangId)->update($vals);
        }

        unset($this->memo[$trx->id]);
    }

    /** @return array<string, mixed> */
    private function compute(Transaksi $trx): array
    {
        if (isset($this->memo[$trx->id])) {
            return $this->memo[$trx->id];
        }

        if (! $trx->akun_lawan_id) {
            throw DomainException::invalidState('Akun lawan stok awal belum ditentukan.');
        }

        $items = $trx->barang()->orderBy('nomor_baris')->get();
        $produk = Produk::whereIn('id', $items->pluck('produk_id')->unique()->sort()->values())
            ->lockForUpdate()->get()->keyBy('id');

        $tanggal = $trx->tanggal->toDateString();
        $intents = [];
        $barangUpdate = [];
        $total = '0';

        foreach ($items as $item) {
            $p = $produk[$item->produk_id];
            $state = $this->ledger->currentState($p->id);

            if ($state['urutan'] > 0) {
                throw DomainException::invalidState("Produk {$p->kode_produk} sudah memiliki mutasi stok; stok awal ditolak (gunakan stok opname).");
            }

            $qty = Decimal::of($item->kuantitas, Decimal::QTY);
            $nilai = Decimal::mul($item->kuantitas, $item->harga);
            $avgAfter = $this->avg->average($nilai, $qty);

            $intents[] = [
                'produk_id' => $p->id,
                'transaksi_id' => $trx->id,
                'transaksi_barang_id' => $item->id,
                'tanggal' => $tanggal,
                'urutan' => 1,
                'jenis' => 'opening',
                'kuantitas_masuk' => $qty,
                'kuantitas_keluar' => Decimal::of(0, Decimal::QTY),
                'nilai_masuk' => $nilai,
                'nilai_keluar' => '0.00',
                'harga_pokok' => $avgAfter,
                'saldo_kuantitas' => $qty,
                'saldo_nilai' => $nilai,
                'harga_pokok_rata' => $avgAfter,
                'source_key' => "OPEN:{$trx->id}:{$item->id}",
            ];

            $barangUpdate[$item->id] = ['harga_pokok' => $avgAfter, 'nilai_pokok' => $nilai, 'subtotal' => $nilai];
            $total = Decimal::add($total, $nilai);
        }

        if (! Decimal::isPositive($total)) {
            throw DomainException::invalidState('Nilai stok awal nol; tidak ada yang diposting.');
        }

        $sys = new SystemAccounts;
        $journal = new JournalPlan(
            new JournalLine($sys->id('inventory'), debit: $total, keterangan: 'Stok awal persediaan'),
            new JournalLine($trx->akun_lawan_id, kredit: $total, keterangan: 'Stok awal persediaan'),
        );

        return $this->memo[$trx->id] = compact('journal', 'total', 'intents', 'produk', 'barangUpdate');
    }
}
