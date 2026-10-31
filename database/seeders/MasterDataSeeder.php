<?php

namespace Database\Seeders;

use App\Models\Akun;
use App\Models\SaldoAwalAkun;
use App\Models\TransaksiDetail;
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
    /** Akun yang jarang dipakai BUMDes: dibuat nonaktif (bisa diaktifkan di menu Akun). */
    public const JARANG_DIPAKAI = ['1.1.06.01', '3.1.02.00', '3.2.03.00'];

    public function run(): void
    {
        DB::transaction(function () {
            // firstOrCreate: nama/penanggung jawab unit yang sudah diedit
            // pengelola tidak ditimpa saat seeder dijalankan ulang.
            foreach ($this->units() as $unit) {
                UnitUsaha::firstOrCreate(['kode_unit' => $unit['kode_unit']], $unit);
            }

            foreach ($this->chartOfAccounts() as $akun) {
                // Akun jarang dipakai dibuat nonaktif; akun lama yang sudah
                // terpakai (jurnal/saldo awal) tetap aktif agar saldonya tidak
                // hilang dari laporan.
                if (in_array($akun['kode_akun'], self::JARANG_DIPAKAI, true)) {
                    $existing = Akun::where('kode_akun', $akun['kode_akun'])->first();
                    $akun['is_active'] = $existing && (TransaksiDetail::where('akun_id', $existing->id)->exists()
                        || SaldoAwalAkun::where('akun_id', $existing->id)->exists());
                }
                Akun::updateOrCreate(['kode_akun' => $akun['kode_akun']], $akun);
            }
        });

        $this->assertSystemKeysPresent();
    }

    /** @return array<int, array<string, mixed>> */
    private function units(): array
    {
        return [
            ['kode_unit' => 'UNIT-KONSUMSI', 'nama_unit' => 'Konsumsi / Jasa Boga', 'jenis_usaha' => 'dagang', 'penanggung_jawab' => 'Pengelola Konsumsi', 'status' => 'aktif'],
            ['kode_unit' => 'UNIT-DAGANG', 'nama_unit' => 'Perdagangan Umum', 'jenis_usaha' => 'dagang', 'penanggung_jawab' => 'Pengelola Dagang', 'status' => 'aktif'],
            ['kode_unit' => 'UNIT-ATK', 'nama_unit' => 'ATK & Fotocopy', 'jenis_usaha' => 'dagang', 'penanggung_jawab' => 'Pengelola ATK', 'status' => 'aktif'],
            ['kode_unit' => 'UNIT-UPAKARA', 'nama_unit' => 'Upakara / Banten', 'jenis_usaha' => 'dagang', 'penanggung_jawab' => 'Pengelola Upakara', 'status' => 'aktif'],
            ['kode_unit' => 'UNIT-PUPUK', 'nama_unit' => 'Pupuk', 'jenis_usaha' => 'dagang', 'penanggung_jawab' => 'Pengelola Pupuk', 'status' => 'aktif'],
            ['kode_unit' => 'UNIT-WISATA', 'nama_unit' => 'Desa Wisata', 'jenis_usaha' => 'jasa', 'penanggung_jawab' => 'Pengelola Desa Wisata', 'status' => 'aktif'],
            ['kode_unit' => 'UNIT-KREDIT', 'nama_unit' => 'Kredit Pedagang Pantai', 'jenis_usaha' => 'kredit', 'penanggung_jawab' => 'Pengelola Kredit', 'status' => 'aktif'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function chartOfAccounts(): array
    {
        // kode, nama, jenis, saldo_normal, system_key, report_group, is_cash
        // Penomoran mengikuti buku BUMDes Munggu; migration renumber memetakan
        // kode lama (1-1010 dst) ke kode ini tanpa mengganti id akun.
        // Akun ber-system_key = peran yang dipakai service (wajib). Akun tanpa
        // system_key = akun bernama tambahan (dana ekuitas, beban rinci, pajak)
        // yang ikut di laporan basis-akun (ReportService/StatementService).
        $rows = [
            ['1.1.01.01', 'Kas Tunai', 'aset', 'debit', 'cash', 'aset_lancar', true],
            ['1.1.01.02', 'Kas di Bank BPD', 'aset', 'debit', 'bank', 'aset_lancar', true],
            ['1.1.03.01', 'Piutang Usaha', 'aset', 'debit', 'trade_receivable', 'aset_lancar', false],
            ['1.1.03.03', 'Piutang Pedagang Pantai', 'aset', 'debit', 'loan_receivable', 'aset_lancar', false],
            ['1.1.05.01', 'Persediaan Barang Dagang', 'aset', 'debit', 'inventory', 'aset_lancar', false],
            ['1.1.06.01', 'Pajak Dibayar di Muka', 'aset', 'debit', 'tax_input', 'aset_lancar', false],
            ['1.3.03.01', 'Inventaris Kantor', 'aset', 'debit', 'fixed_asset', 'aset_tetap', false],
            ['1.3.07.01', 'Akm Penyusutan Inventaris', 'aset', 'kredit', 'accumulated_depreciation', 'aset_tetap', false],
            // "Aktiva" pada neraca BUMDes (sepeda, bebek-bebekan, baliho) — dipisah dari inventaris kantor.
            ['1.3.04.01', 'Aktiva Tetap Usaha', 'aset', 'debit', null, 'aset_tetap', false],
            ['1.3.08.01', 'Akm Penyusutan Aktiva Tetap Usaha', 'aset', 'kredit', null, 'aset_tetap', false],
            ['2.1.00.01', 'Utang Usaha', 'kewajiban', 'kredit', 'trade_payable', 'kewajiban', false],
            ['2.1.00.02', 'Utang Gaji', 'kewajiban', 'kredit', null, 'kewajiban', false],
            ['2.1.00.03', 'Utang Pajak', 'kewajiban', 'kredit', 'tax_output', 'kewajiban', false],
            ['3.1.01.00', 'Modal Desa Munggu', 'modal', 'kredit', 'capital', 'ekuitas', false],
            ['3.1.02.00', 'Modal Ketahanan Pangan', 'modal', 'kredit', null, 'ekuitas', false],
            ['3.2.01.00', 'Dana Cadangan', 'modal', 'kredit', null, 'ekuitas', false],
            ['3.2.02.00', 'Dana Pendidikan', 'modal', 'kredit', null, 'ekuitas', false],
            ['3.2.03.00', 'Dana Pelatihan', 'modal', 'kredit', null, 'ekuitas', false],
            ['3.2.10.00', 'Dana Sosial', 'modal', 'kredit', null, 'ekuitas', false],
            ['3.3.01.00', 'Laba Ditahan', 'modal', 'kredit', 'retained_earnings', 'ekuitas', false],
            ['4.1.1', 'Penjualan Jasa Konsumsi', 'pendapatan', 'kredit', 'sales_konsumsi', 'pendapatan', false],
            ['4.1.2', 'Penjualan Perdagangan', 'pendapatan', 'kredit', 'sales', 'pendapatan', false],
            ['4.1.3', 'Penjualan ATK', 'pendapatan', 'kredit', 'sales_atk', 'pendapatan', false],
            ['4.1.4', 'Penjualan Upakara', 'pendapatan', 'kredit', 'sales_upakara', 'pendapatan', false],
            ['4.1.5', 'Penjualan Pupuk', 'pendapatan', 'kredit', 'sales_pupuk', 'pendapatan', false],
            ['4.4.1', 'Pendapatan Bunga Bank', 'pendapatan', 'kredit', null, 'pendapatan_lain', false],
            ['4.4.2', 'Pendapatan Desa Wisata', 'pendapatan', 'kredit', null, 'pendapatan_lain', false],
            ['4.4.3', 'Pendapatan Fee Pupuk', 'pendapatan', 'kredit', null, 'pendapatan_lain', false],
            ['4.4.4', 'Pendapatan Bunga Pedagang Pantai', 'pendapatan', 'kredit', 'interest_income', 'pendapatan_lain', false],
            ['4.4.5', 'Pendapatan Denda Kredit', 'pendapatan', 'kredit', 'penalty_income', 'pendapatan_lain', false],
            ['4.4.8', 'Pendapatan Lain-lain', 'pendapatan', 'kredit', null, 'pendapatan_lain', false],
            ['4.4.9', 'Keuntungan Selisih Persediaan', 'pendapatan', 'kredit', 'inventory_gain', 'pendapatan_lain', false],
            ['5.1.1', 'HPP Konsumsi', 'hpp', 'debit', 'cogs_konsumsi', 'hpp', false],
            ['5.1.2', 'HPP Perdagangan', 'hpp', 'debit', 'cogs', 'hpp', false],
            ['5.1.3', 'HPP ATK', 'hpp', 'debit', 'cogs_atk', 'hpp', false],
            ['5.1.4', 'HPP Upakara', 'hpp', 'debit', 'cogs_upakara', 'hpp', false],
            ['5.1.5', 'HPP Pupuk', 'hpp', 'debit', 'cogs_pupuk', 'hpp', false],
            ['6.1.1', 'Biaya Pegawai', 'beban', 'debit', null, 'beban', false],
            ['6.1.2', 'Biaya Transport', 'beban', 'debit', null, 'beban', false],
            ['6.1.3', 'Biaya Kantor', 'beban', 'debit', null, 'beban', false],
            ['6.1.4', 'Biaya Penyusutan', 'beban', 'debit', 'depreciation_expense', 'beban', false],
            ['6.1.5', 'BPJS Ketenagakerjaan', 'beban', 'debit', null, 'beban', false],
            ['6.1.9', 'Biaya Operasional Lain-lain', 'beban', 'debit', 'operating_expense', 'beban', false],
            ['6.2.1', 'Pajak Bunga Bank', 'beban', 'debit', null, 'beban_non_operasional', false],
            ['6.2.2', 'Administrasi Bank', 'beban', 'debit', null, 'beban_non_operasional', false],
            ['6.2.9', 'Kerugian Selisih Persediaan', 'beban', 'debit', 'inventory_loss', 'beban_non_operasional', false],
            ['8.0', 'Pajak', 'beban', 'debit', null, 'beban_pajak', false],
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
