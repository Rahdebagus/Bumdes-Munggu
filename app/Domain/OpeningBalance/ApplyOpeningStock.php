<?php

namespace App\Domain\OpeningBalance;

use App\Domain\Inventory\MovingAverageCost;
use App\Domain\Inventory\StockLedger;
use App\Domain\Shared\Decimal;
use App\Enums\SaldoRincianJenis;
use App\Models\MutasiStok;
use App\Models\PeriodeAkuntansi;
use App\Models\Produk;
use App\Models\SaldoAwalRincian;
use Illuminate\Validation\ValidationException;

/**
 * Stok awal dari rincian saldo awal (dokumen05: mutasi jenis opening bersumber
 * rincian saldo awal terkunci). Rincian stok yang menyebut produk + kuantitas
 * menjadi mutasi pertama produk tersebut tanpa jurnal — nilainya sudah ada di
 * saldo awal akun Persediaan — sehingga subledger stok = akun persediaan.
 * Rincian stok tanpa produk/kuantitas (isian manual per kode) dilewati.
 */
class ApplyOpeningStock
{
    public function __construct(private StockLedger $ledger, private MovingAverageCost $avg) {}

    /** Dipanggil di dalam transaksi penguncian. @return int jumlah produk yang diberi stok awal */
    public function handle(PeriodeAkuntansi $periode): int
    {
        $rincian = SaldoAwalRincian::query()
            ->where('jenis', SaldoRincianJenis::Stok->value)
            ->whereNotNull('produk_id')
            ->where('kuantitas', '>', 0)
            ->whereHas('saldoAwalAkun', fn ($q) => $q->where('periode_id', $periode->id))
            ->orderBy('produk_id')
            ->get();

        $produk = Produk::whereIn('id', $rincian->pluck('produk_id')->unique()->values())
            ->lockForUpdate()->get()->keyBy('id');

        $tanggal = $periode->tanggal_mulai->toDateString();
        $count = 0;

        foreach ($rincian->groupBy('produk_id') as $produkId => $rows) {
            $p = $produk[$produkId];

            if ($rows->count() > 1) {
                throw ValidationException::withMessages(['lock' => "Produk {$p->kode_produk} punya lebih dari satu rincian stok awal."]);
            }
            if (! $p->lacak_stok) {
                throw ValidationException::withMessages(['lock' => "Produk {$p->kode_produk} tidak dilacak stoknya; hapus rincian stoknya."]);
            }
            if (MutasiStok::where('produk_id', $p->id)->exists()) {
                throw ValidationException::withMessages(['lock' => "Produk {$p->kode_produk} sudah memiliki mutasi stok; stok awal ganda ditolak."]);
            }

            $r = $rows->first();
            $qty = Decimal::of((string) $r->kuantitas, Decimal::QTY);
            $nilai = Decimal::of((string) $r->nominal);
            $avg = $this->avg->average($nilai, $qty);

            $this->ledger->persist($p, [
                'produk_id' => $p->id,
                'opening_rincian_id' => $r->id,
                'tanggal' => $tanggal,
                'urutan' => 1,
                'jenis' => 'opening',
                'kuantitas_masuk' => $qty,
                'kuantitas_keluar' => Decimal::of(0, Decimal::QTY),
                'nilai_masuk' => $nilai,
                'nilai_keluar' => '0.00',
                'harga_pokok' => $avg,
                'saldo_kuantitas' => $qty,
                'saldo_nilai' => $nilai,
                'harga_pokok_rata' => $avg,
                'source_key' => "OPEN-RINCIAN:{$r->id}",
            ]);
            $count++;
        }

        return $count;
    }
}
