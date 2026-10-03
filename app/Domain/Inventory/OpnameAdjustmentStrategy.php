<?php

namespace App\Domain\Inventory;

use App\Domain\Accounting\Contracts\PostingStrategy;
use App\Domain\Accounting\JournalLine;
use App\Domain\Accounting\JournalPlan;
use App\Domain\Accounting\SystemAccounts;
use App\Domain\Shared\Decimal;
use App\Domain\Shared\DomainException;
use App\Models\Produk;
use App\Models\StokOpname;
use App\Models\Transaksi;

/**
 * Posting stok opname (dokumen08 §2/§4). Selisih kurang → Dr Beban selisih
 * persediaan; Cr Persediaan. Selisih lebih → Dr Persediaan; Cr Pendapatan
 * selisih persediaan. Stale detection: stock_version produk harus sama dengan
 * snapshot saat hitung; bila ada mutasi baru, posting ditolak.
 */
class OpnameAdjustmentStrategy implements PostingStrategy
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

        // Tautkan detail opname ke transaksi penyesuaian.
        StokOpname::find($trx->sumber_id)?->details()
            ->whereColumn('stok_sistem', '!=', 'stok_fisik')
            ->update(['transaksi_id' => $trx->id]);

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

        $opname = StokOpname::with('details')->findOrFail($trx->sumber_id);
        $details = $opname->details->filter(fn ($d) => ! Decimal::isZero((string) $d->selisih, Decimal::QTY));
        $produk = Produk::whereIn('id', $details->pluck('produk_id')->unique()->sort()->values())
            ->lockForUpdate()->get()->keyBy('id');

        $gain = '0';
        $loss = '0';
        $intents = [];

        foreach ($details as $d) {
            $p = $produk[$d->produk_id];

            if ((int) $p->stock_version !== (int) $d->stock_version_snapshot) {
                throw DomainException::staleSnapshot(
                    "Stok produk {$p->kode_produk} berubah sejak opname dihitung; hitung ulang & setujui kembali."
                );
            }

            $state = $this->ledger->currentState($p->id);
            $urutan = $state['urutan'] + 1;
            $selisih = (string) $d->selisih;

            if (Decimal::isPositive($selisih, Decimal::QTY)) {
                $nilai = Decimal::mul($selisih, (string) $d->harga_pokok);
                $saldoQty = Decimal::add($state['qty'], $selisih, Decimal::QTY);
                $saldoNilai = Decimal::add($state['nilai'], $nilai);
                $masuk = $selisih;
                $keluar = Decimal::of(0, Decimal::QTY);
                $nilaiMasuk = $nilai;
                $nilaiKeluar = '0.00';
                $gain = Decimal::add($gain, $nilai);
            } else {
                $qtyOut = Decimal::sub(0, $selisih, Decimal::QTY); // abs
                $nilai = $this->avg->nilaiKeluar($qtyOut, $state['qty'], $state['nilai']);
                $saldoQty = Decimal::sub($state['qty'], $qtyOut, Decimal::QTY);
                $saldoNilai = Decimal::sub($state['nilai'], $nilai);
                $masuk = Decimal::of(0, Decimal::QTY);
                $keluar = $qtyOut;
                $nilaiMasuk = '0.00';
                $nilaiKeluar = $nilai;
                $loss = Decimal::add($loss, $nilai);
            }

            $intents[] = [
                'produk_id' => $p->id,
                'transaksi_id' => $trx->id,
                'tanggal' => $trx->tanggal->toDateString(),
                'urutan' => $urutan,
                'jenis' => 'opname',
                'kuantitas_masuk' => $masuk,
                'kuantitas_keluar' => $keluar,
                'nilai_masuk' => $nilaiMasuk,
                'nilai_keluar' => $nilaiKeluar,
                'harga_pokok' => (string) $d->harga_pokok,
                'saldo_kuantitas' => $saldoQty,
                'saldo_nilai' => $saldoNilai,
                'harga_pokok_rata' => $this->avg->average($saldoNilai, $saldoQty),
                'source_key' => "OPN:{$opname->id}:{$p->id}",
            ];
        }

        if (Decimal::isZero($gain) && Decimal::isZero($loss)) {
            throw DomainException::invalidState('Opname tidak memiliki selisih untuk diposting.');
        }

        $sys = new SystemAccounts;
        $lines = [];
        if (Decimal::isPositive($gain)) {
            $lines[] = new JournalLine($sys->id('inventory'), debit: $gain, keterangan: 'Selisih lebih persediaan');
            $lines[] = new JournalLine($sys->id('inventory_gain'), kredit: $gain, keterangan: 'Pendapatan selisih persediaan');
        }
        if (Decimal::isPositive($loss)) {
            $lines[] = new JournalLine($sys->id('inventory_loss'), debit: $loss, keterangan: 'Beban selisih persediaan');
            $lines[] = new JournalLine($sys->id('inventory'), kredit: $loss, keterangan: 'Selisih kurang persediaan');
        }

        return $this->memo[$trx->id] = [
            'journal' => new JournalPlan(...$lines),
            'total' => Decimal::add($gain, $loss),
            'intents' => $intents,
            'produk' => $produk,
        ];
    }
}
