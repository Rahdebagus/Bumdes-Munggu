<?php

namespace App\Domain\Trading;

use App\Domain\Accounting\Contracts\PostingStrategy;
use App\Domain\Accounting\JournalLine;
use App\Domain\Accounting\JournalPlan;
use App\Domain\Accounting\SystemAccounts;
use App\Domain\Inventory\MovingAverageCost;
use App\Domain\Inventory\StockLedger;
use App\Domain\Shared\Decimal;
use App\Domain\Shared\DomainException;
use App\Models\Produk;
use App\Models\Transaksi;
use App\Models\TransaksiBarang;

/**
 * Penjualan tunai/kredit (dokumen08 §2). Dr Kas/bank (tunai) atau Piutang usaha
 * (kredit); Cr Penjualan (net) + pajak keluaran. Dr HPP; Cr Persediaan sebesar
 * nilai keluar aktual (moving average). Stok berkurang, tidak boleh negatif.
 * Akun penjualan & HPP dipilih per kategori produk (config
 * accounting.product_categories); produk jasa (lacak_stok=false) hanya
 * mencatat pendapatan, tanpa mutasi stok/HPP.
 */
class SalePostingStrategy implements PostingStrategy
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
            $this->ledger->persist($c['produk'][$intent['produk_id']], $intent);
        }

        foreach ($c['barangUpdate'] as $barangId => $vals) {
            TransaksiBarang::whereKey($barangId)->update($vals);
        }

        unset($this->memo[$trx->id]);
    }

    /**
     * @return array<string, mixed>
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
        $revenue = [];    // net per system_key akun penjualan
        $hpp = [];        // HPP per system_key akun HPP
        $tax = '0';       // pajak keluaran
        $hppTotal = '0';  // total HPP
        $total = '0';     // total tagihan

        foreach ($items as $item) {
            $pid = $item->produk_id;
            $p = $produk[$pid];
            $net = Decimal::sub(Decimal::mul($item->kuantitas, $item->harga), $item->diskon);
            $subtotal = Decimal::add($net, $item->pajak);
            $salesKey = $p->salesKey();
            $revenue[$salesKey] = Decimal::add($revenue[$salesKey] ?? '0', $net);
            $tax = Decimal::add($tax, $item->pajak);
            $total = Decimal::add($total, $subtotal);

            if (! $p->lacak_stok) {
                // Jasa: tanpa stok & HPP.
                $barangUpdate[$item->id] = ['harga_pokok' => '0', 'nilai_pokok' => '0', 'subtotal' => $subtotal];

                continue;
            }

            $qtyOut = Decimal::of($item->kuantitas, Decimal::QTY);
            $qtyBefore = $running[$pid]['qty'];
            $nilaiBefore = $running[$pid]['nilai'];

            if (Decimal::compare($qtyOut, $qtyBefore, Decimal::QTY) > 0) {
                throw DomainException::insufficientStock(
                    "Stok produk {$produk[$pid]->kode_produk} tidak cukup (butuh {$qtyOut}, tersedia {$qtyBefore})."
                );
            }

            $nilaiKeluar = $this->avg->nilaiKeluar($qtyOut, $qtyBefore, $nilaiBefore);
            $avgUsed = $this->avg->average($nilaiBefore, $qtyBefore);
            $saldoQty = Decimal::sub($qtyBefore, $qtyOut, Decimal::QTY);
            $saldoNilai = Decimal::sub($nilaiBefore, $nilaiKeluar);
            $avgAfter = $this->avg->average($saldoNilai, $saldoQty);
            $urutan = $running[$pid]['urutan'] + 1;

            $intents[] = [
                'produk_id' => $pid,
                'transaksi_id' => $trx->id,
                'transaksi_barang_id' => $item->id,
                'tanggal' => $tanggal,
                'urutan' => $urutan,
                'jenis' => 'penjualan',
                'kuantitas_masuk' => Decimal::of(0, Decimal::QTY),
                'kuantitas_keluar' => $qtyOut,
                'nilai_masuk' => '0.00',
                'nilai_keluar' => $nilaiKeluar,
                'harga_pokok' => $avgUsed,
                'saldo_kuantitas' => $saldoQty,
                'saldo_nilai' => $saldoNilai,
                'harga_pokok_rata' => $avgAfter,
                'source_key' => "SALE:{$trx->id}:{$item->id}",
            ];

            $barangUpdate[$item->id] = [
                'harga_pokok' => $avgUsed,
                'nilai_pokok' => $nilaiKeluar,
                'subtotal' => $subtotal,
            ];

            $running[$pid] = ['qty' => $saldoQty, 'nilai' => $saldoNilai, 'urutan' => $urutan, 'lastDate' => $tanggal];
            $cogsKey = $p->cogsKey();
            $hpp[$cogsKey] = Decimal::add($hpp[$cogsKey] ?? '0', $nilaiKeluar);
            $hppTotal = Decimal::add($hppTotal, $nilaiKeluar);
        }

        $debitId = $trx->metode_pembayaran === 'kredit'
            ? $sys->id('trade_receivable')
            : ($trx->akun_kas_id ?? $sys->id('cash'));

        $lines = [new JournalLine($debitId, debit: $total, keterangan: 'Penjualan', kelompokArusKas: 'operasi')];
        $revenue = array_filter($revenue, fn ($v) => Decimal::isPositive($v)) ?: ['sales' => '0'];
        foreach ($revenue as $key => $amount) {
            $lines[] = new JournalLine($sys->id($key), kredit: $amount, keterangan: 'Pendapatan penjualan');
        }
        if (Decimal::isPositive($tax)) {
            $lines[] = new JournalLine($sys->id('tax_output'), kredit: $tax, keterangan: 'Pajak keluaran');
        }
        // HPP per kategori (hanya jika ada nilai keluar)
        foreach ($hpp as $key => $amount) {
            if (Decimal::isPositive($amount)) {
                $lines[] = new JournalLine($sys->id($key), debit: $amount, keterangan: 'HPP');
            }
        }
        if (Decimal::isPositive($hppTotal)) {
            $lines[] = new JournalLine($sys->id('inventory'), kredit: $hppTotal, keterangan: 'Persediaan (HPP)');
        }

        $journal = new JournalPlan(...$lines);

        return $this->memo[$trx->id] = compact('journal', 'total', 'intents', 'produk', 'barangUpdate');
    }
}
