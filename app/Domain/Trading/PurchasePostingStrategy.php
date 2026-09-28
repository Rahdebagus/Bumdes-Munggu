<?php

namespace App\Domain\Trading;

use App\Domain\Accounting\Contracts\PostingStrategy;
use App\Domain\Accounting\JournalLine;
use App\Domain\Accounting\JournalPlan;
use App\Domain\Accounting\SystemAccounts;
use App\Domain\Inventory\MovingAverageCost;
use App\Domain\Inventory\StockLedger;
use App\Domain\Shared\Decimal;
use App\Models\Produk;
use App\Models\Transaksi;
use App\Models\TransaksiBarang;

/**
 * Pembelian tunai/kredit (dokumen08 §2). Dr Persediaan; Cr Kas/bank (tunai)
 * atau Utang usaha (kredit). Stok bertambah; moving average dihitung ulang.
 * Pajak masukan default digabung ke biaya persediaan (mode kreditkan = tahap
 * lanjutan, eksplisit).
 */
class PurchasePostingStrategy implements PostingStrategy
{
    /** @var array<int, array<string, mixed>> */
    private array $memo = [];

    public function __construct(
        private StockLedger $ledger,
        private MovingAverageCost $avg,
    ) {}

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
            $produk = $c['produk'][$intent['produk_id']];
            $this->ledger->persist($produk, $intent);
        }

        foreach ($c['barangUpdate'] as $barangId => $vals) {
            TransaksiBarang::whereKey($barangId)->update($vals);
        }

        unset($this->memo[$trx->id]);
    }

    /**
     * @return array{journal: JournalPlan, total: string, intents: array<int, array<string, mixed>>, produk: array<int, Produk>, barangUpdate: array<int, array<string, string>>}
     */
    private function compute(Transaksi $trx): array
    {
        if (isset($this->memo[$trx->id])) {
            return $this->memo[$trx->id];
        }

        $sys = new SystemAccounts;
        $items = $trx->barang()->orderBy('nomor_baris')->get();
        $produkIds = $items->pluck('produk_id')->unique()->sort()->values();
        $produk = Produk::whereIn('id', $produkIds)->lockForUpdate()->get()->keyBy('id');

        $running = [];
        foreach ($produkIds as $pid) {
            $running[$pid] = $this->ledger->currentState($pid);
        }

        $tanggal = $trx->tanggal->toDateString();
        $intents = [];
        $barangUpdate = [];
        $inventoryTotal = '0';
        $total = '0';

        foreach ($items as $item) {
            $pid = $item->produk_id;
            $net = Decimal::sub(Decimal::mul($item->kuantitas, $item->harga), $item->diskon);
            $subtotal = Decimal::add($net, $item->pajak);
            $nilaiMasuk = $subtotal; // pajak non-kreditkan → bagian biaya persediaan

            $qtyBefore = $running[$pid]['qty'];
            $nilaiBefore = $running[$pid]['nilai'];
            $urutan = $running[$pid]['urutan'] + 1;

            $saldoQty = Decimal::add($qtyBefore, $item->kuantitas, Decimal::QTY);
            $saldoNilai = Decimal::add($nilaiBefore, $nilaiMasuk);
            $avgAfter = $this->avg->average($saldoNilai, $saldoQty);

            $intents[] = [
                'produk_id' => $pid,
                'transaksi_id' => $trx->id,
                'transaksi_barang_id' => $item->id,
                'tanggal' => $tanggal,
                'urutan' => $urutan,
                'jenis' => 'pembelian',
                'kuantitas_masuk' => Decimal::of($item->kuantitas, Decimal::QTY),
                'kuantitas_keluar' => Decimal::of(0, Decimal::QTY),
                'nilai_masuk' => $nilaiMasuk,
                'nilai_keluar' => '0.00',
                'harga_pokok' => $avgAfter,
                'saldo_kuantitas' => $saldoQty,
                'saldo_nilai' => $saldoNilai,
                'harga_pokok_rata' => $avgAfter,
                'source_key' => "PUR:{$trx->id}:{$item->id}",
            ];

            $unitCost = $this->avg->average($nilaiMasuk, $item->kuantitas);
            $barangUpdate[$item->id] = [
                'harga_pokok' => $unitCost,
                'nilai_pokok' => $nilaiMasuk,
                'subtotal' => $subtotal,
            ];

            $running[$pid] = ['qty' => $saldoQty, 'nilai' => $saldoNilai, 'urutan' => $urutan, 'lastDate' => $tanggal];
            $inventoryTotal = Decimal::add($inventoryTotal, $nilaiMasuk);
            $total = Decimal::add($total, $subtotal);
        }

        $lawanId = $trx->metode_pembayaran === 'kredit'
            ? $sys->id('trade_payable')
            : ($trx->akun_kas_id ?? $sys->id('cash'));

        $journal = new JournalPlan(
            new JournalLine($sys->id('inventory'), debit: $inventoryTotal, keterangan: 'Persediaan (pembelian)'),
            new JournalLine($lawanId, kredit: $total, keterangan: 'Pembelian', kelompokArusKas: 'operasi'),
        );

        return $this->memo[$trx->id] = compact('journal', 'total', 'intents', 'produk', 'barangUpdate');
    }
}
