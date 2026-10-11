<?php

namespace Database\Seeders;

use App\Models\Akun;
use App\Models\UnitUsaha;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Master data produksi: unit demo minimal + chart of accounts lengkap dengan
 * seluruh system_key yang dipakai service. Idempotent (updateOrCreate) dan
 * dijalankan dalam satu transaksi (dokumen05 §Aturan migration langkah 5).
 * TIDAK berisi transaksi/pihak fiktif — itu tugas DemoSeeder.
 */
class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            foreach ($this->units() as $unit) {
                UnitUsaha::updateOrCreate(['kode_unit' => $unit['kode_unit']], $unit);
            }

            foreach ($this->chartOfAccounts() as $akun) {
                Akun::updateOrCreate(['kode_akun' => $akun['kode_akun']], $akun);
            }
        });

        $this->assertSystemKeysPresent();
    }

    /** @return array<int, array<string, mixed>> */
    private function units(): array
    {
        return [
            ['kode_unit' => 'UNIT-DAGANG', 'nama_unit' => 'Unit Usaha Dagang', 'jenis_usaha' => 'dagang', 'penanggung_jawab' => 'Pengelola Dagang', 'status' => 'aktif'],
            ['kode_unit' => 'UNIT-KREDIT', 'nama_unit' => 'Unit Usaha Simpan Pinjam', 'jenis_usaha' => 'kredit', 'penanggung_jawab' => 'Pengelola Kredit', 'status' => 'aktif'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function chartOfAccounts(): array
    {
        // kode, nama, jenis, saldo_normal, system_key, report_group, is_cash
        // Akun ber-system_key = peran yang dipakai service (wajib). Akun tanpa
        // system_key = akun bernama tambahan (dana ekuitas, beban rinci, pajak)
        // yang ikut di laporan basis-akun (ReportService/StatementService).
        $rows = [
            ['1-1010', 'Kas', 'aset', 'debit', 'cash', 'aset_lancar', true],
            ['1-1020', 'Bank BUMDes', 'aset', 'debit', 'bank', 'aset_lancar', true],
            ['1-1030', 'Persediaan Barang', 'aset', 'debit', 'inventory', 'aset_lancar', false],
            ['1-1040', 'Piutang Usaha', 'aset', 'debit', 'trade_receivable', 'aset_lancar', false],
            ['1-1050', 'Piutang Pinjaman', 'aset', 'debit', 'loan_receivable', 'aset_lancar', false],
            ['1-1060', 'Pajak Dibayar di Muka', 'aset', 'debit', 'tax_input', 'aset_lancar', false],
            ['1-1500', 'Inventaris', 'aset', 'debit', 'fixed_asset', 'aset_tetap', false],
            ['1-1600', 'Akumulasi Penyusutan Inventaris', 'aset', 'kredit', 'accumulated_depreciation', 'aset_tetap', false],
            ['2-2010', 'Utang Usaha', 'kewajiban', 'kredit', 'trade_payable', 'kewajiban', false],
            ['2-2020', 'Utang Pajak', 'kewajiban', 'kredit', 'tax_output', 'kewajiban', false],
            ['3-3010', 'Modal Dana Desa', 'modal', 'kredit', 'capital', 'ekuitas', false],
            ['3-3020', 'Laba Ditahan', 'modal', 'kredit', 'retained_earnings', 'ekuitas', false],
            ['3-3030', 'Modal Ketahanan Pangan', 'modal', 'kredit', null, 'ekuitas', false],
            ['3-3040', 'Dana Cadangan', 'modal', 'kredit', null, 'ekuitas', false],
            ['3-3050', 'Dana Pendidikan', 'modal', 'kredit', null, 'ekuitas', false],
            ['3-3060', 'Dana Sosial', 'modal', 'kredit', null, 'ekuitas', false],
            ['3-3070', 'Dana Pelatihan', 'modal', 'kredit', null, 'ekuitas', false],
            ['4-4010', 'Pendapatan Penjualan', 'pendapatan', 'kredit', 'sales', 'pendapatan', false],
            ['4-4020', 'Pendapatan Bunga Kredit', 'pendapatan', 'kredit', 'interest_income', 'pendapatan_lain', false],
            ['4-4030', 'Pendapatan Denda', 'pendapatan', 'kredit', 'penalty_income', 'pendapatan_lain', false],
            ['4-4040', 'Keuntungan Selisih Persediaan', 'pendapatan', 'kredit', 'inventory_gain', 'pendapatan_lain', false],
            ['4-4050', 'Pendapatan Bunga Bank', 'pendapatan', 'kredit', null, 'pendapatan_lain', false],
            ['5-5010', 'Harga Pokok Penjualan', 'hpp', 'debit', 'cogs', 'hpp', false],
            ['6-6010', 'Beban Operasional', 'beban', 'debit', 'operating_expense', 'beban', false],
            ['6-6020', 'Beban Penyusutan Inventaris', 'beban', 'debit', 'depreciation_expense', 'beban', false],
            ['6-6030', 'Kerugian Selisih Persediaan', 'beban', 'debit', 'inventory_loss', 'beban', false],
            ['6-6040', 'Beban Gaji Pegawai', 'beban', 'debit', null, 'beban', false],
            ['6-6050', 'Beban Transportasi', 'beban', 'debit', null, 'beban', false],
            ['6-6060', 'Beban Biaya Kantor', 'beban', 'debit', null, 'beban', false],
            ['6-6070', 'Administrasi Bank', 'beban', 'debit', null, 'beban', false],
            ['6-6080', 'Pajak Bunga Bank', 'beban', 'debit', null, 'beban', false],
            ['6-6090', 'Pajak Usaha', 'beban', 'debit', null, 'beban_pajak', false],
        ];

        return array_map(fn ($r) => [
            'kode_akun' => $r[0],
            'nama_akun' => $r[1],
            'jenis_akun' => $r[2],
            'saldo_normal' => $r[3],
            'system_key' => $r[4],
            'report_group' => $r[5],
            'is_cash' => $r[6],
            'is_active' => true,
        ], $rows);
    }

    private function assertSystemKeysPresent(): void
    {
        $required = config('accounting.system_keys', []);
        $present = Akun::whereIn('system_key', $required)->where('is_active', true)->pluck('system_key')->all();
        $missing = array_diff($required, $present);

        if ($missing !== []) {
            throw new RuntimeException('system_key belum lengkap/aktif: '.implode(', ', $missing));
        }
    }
}
