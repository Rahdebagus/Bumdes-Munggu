<?php

namespace App\Domain\Inventory;

use App\Domain\Accounting\PostDocument;
use App\Domain\Accounting\SystemAccounts;
use App\Domain\Shared\DocumentNumber;
use App\Domain\Shared\DomainException;
use App\Enums\TransaksiJenis;
use App\Enums\TransaksiStatus;
use App\Models\MutasiStok;
use App\Models\PeriodeAkuntansi;
use App\Models\Produk;
use App\Models\Transaksi;
use App\Models\TransaksiBarang;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Membuat & memposting stok awal: satu dokumen per unit usaha (agar laporan
 * per unit benar). Produk yang sudah punya mutasi stok dilewati. Jurnal:
 * Dr Persediaan; Cr akun lawan (default Modal Desa — system_key capital).
 *
 * PERHATIAN: bila saldo awal (menu Saldo Awal) sudah memuat nilai Persediaan,
 * pakai akun lawan lain atau kosongkan persediaan di saldo awal agar tidak
 * tercatat dua kali.
 */
class PostOpeningStock
{
    public function __construct(private PostDocument $poster) {}

    /**
     * @param  array<int, array{produk: Produk, kuantitas: string, harga: string}>  $lines
     * @return array{transaksi: array<int, Transaksi>, dilewati: array<int, string>}
     */
    public function handle(array $lines, string $tanggal, User $actor, ?int $akunLawanId = null): array
    {
        $periode = PeriodeAkuntansi::whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal)->first();
        if (! $periode) {
            throw DomainException::invalidState("Tidak ada periode akuntansi untuk tanggal stok awal {$tanggal}. Buat periodenya di menu Periode Akuntansi.");
        }

        $akunLawanId ??= (new SystemAccounts)->id('capital');
        $dilewati = [];
        $perUnit = [];

        foreach ($lines as $line) {
            // Baca ulang dari DB: default kolom (lacak_stok) belum ada di instance baru.
            $p = Produk::findOrFail($line['produk']->id);
            $line['produk'] = $p;
            if (! $p->lacak_stok || (float) $line['kuantitas'] <= 0) {
                continue;
            }
            if (MutasiStok::where('produk_id', $p->id)->exists()) {
                $dilewati[] = "{$p->kode_produk} {$p->nama_produk}: sudah punya mutasi stok";

                continue;
            }
            $perUnit[$p->unit_usaha_id][] = $line;
        }

        $hasil = [];
        foreach ($perUnit as $unitId => $unitLines) {
            $trx = DB::transaction(function () use ($unitId, $unitLines, $tanggal, $periode, $actor, $akunLawanId) {
                $trx = Transaksi::create([
                    'nomor_transaksi' => DocumentNumber::next('SA', $tanggal),
                    'tanggal' => $tanggal,
                    'periode_id' => $periode->id,
                    'unit_usaha_id' => $unitId,
                    'user_id' => $actor->id,
                    'jenis_transaksi' => TransaksiJenis::Penyesuaian->value,
                    'sumber_tipe' => 'stok_awal',
                    'status' => TransaksiStatus::Draft->value,
                    'akun_lawan_id' => $akunLawanId,
                    'keterangan' => 'Stok awal persediaan',
                    'request_key' => (string) Str::uuid(),
                    'version' => 1,
                ]);

                $nomor = 1;
                foreach ($unitLines as $line) {
                    TransaksiBarang::create([
                        'transaksi_id' => $trx->id,
                        'produk_id' => $line['produk']->id,
                        'kuantitas' => $line['kuantitas'],
                        'harga' => $line['harga'],
                        'diskon' => 0,
                        'pajak' => 0,
                        'subtotal' => 0,
                        'nomor_baris' => $nomor++,
                    ]);
                }

                return $trx;
            });

            $hasil[] = $this->poster->handle($trx, $actor);
        }

        return ['transaksi' => $hasil, 'dilewati' => $dilewati];
    }
}
