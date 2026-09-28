<?php

namespace Database\Seeders;

use App\Models\PeriodeAkuntansi;
use App\Models\Pihak;
use App\Models\Produk;
use App\Models\UnitUsaha;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Data demo sintetis untuk pengembangan/UAT — TERPISAH dari produksi dan
 * MENOLAK berjalan di environment production (dokumen13 tahap02). Tidak
 * mengarang saldo/transaksi nyata; hanya master demo agar UI dapat diuji.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DemoSeeder tidak boleh dijalankan di production.');
        }

        // Master produksi harus ada lebih dulu.
        $this->call(MasterDataSeeder::class);

        $dagang = UnitUsaha::where('kode_unit', 'UNIT-DAGANG')->firstOrFail();

        PeriodeAkuntansi::firstOrCreate(
            ['tahun' => 2026, 'bulan' => 1],
            ['tanggal_mulai' => '2026-01-01', 'tanggal_selesai' => '2026-01-31', 'status' => 'terbuka', 'revision' => 0],
        );

        $pihak = [
            ['kode_pihak' => 'CUST-001', 'jenis_pihak' => 'pelanggan', 'nama' => 'Pelanggan Demo', 'status' => 'aktif'],
            ['kode_pihak' => 'SUPP-001', 'jenis_pihak' => 'pemasok', 'nama' => 'Pemasok Demo', 'status' => 'aktif'],
            ['kode_pihak' => 'PMJ-001', 'jenis_pihak' => 'peminjam', 'nama' => 'Peminjam Demo', 'status' => 'aktif'],
        ];
        foreach ($pihak as $p) {
            Pihak::updateOrCreate(['kode_pihak' => $p['kode_pihak']], $p);
        }

        $produk = [
            ['kode_produk' => 'BRG-001', 'nama_produk' => 'Beras 5kg', 'satuan' => 'sak', 'harga_beli' => 60_000, 'harga_jual' => 68_000],
            ['kode_produk' => 'BRG-002', 'nama_produk' => 'Minyak Goreng 1L', 'satuan' => 'pcs', 'harga_beli' => 15_000, 'harga_jual' => 18_000],
        ];
        foreach ($produk as $b) {
            Produk::updateOrCreate(
                ['kode_produk' => $b['kode_produk']],
                array_merge($b, ['unit_usaha_id' => $dagang->id, 'status' => 'aktif']),
            );
        }
    }
}
