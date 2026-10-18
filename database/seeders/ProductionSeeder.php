<?php

namespace Database\Seeders;

use App\Models\Akun;
use App\Models\PeriodeAkuntansi;
use App\Models\Pihak;
use App\Models\Produk;
use App\Models\Transaksi;
use App\Models\UnitUsaha;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Bootstrap PRODUKSI untuk server baru — satu perintah, tanpa satu pun
 * transaksi/pihak/produk fiktif (itu tugas DemoSeeder yang menolak production).
 *
 *   php artisan migrate --force
 *   php artisan db:seed --class=ProductionSeeder --force
 *
 * Isi:
 *   1. MasterDataSeeder — unit usaha + chart of accounts lengkap (system_key).
 *   2. UserSeeder       — akun login Admin & Pimpinan (identitas & password
 *                         dari .env, lihat config/seed.php).
 *   3. Periode go-live  — opsional, dari SEED_PERIODE_AWAL (format YYYY-MM).
 *
 * Idempotent: aman dijalankan ulang. Master data memakai updateOrCreate, akun
 * login yang sudah ada TIDAK diubah (password/role/nama), periode tidak
 * digandakan.
 *
 * Preflight: seluruh konfigurasi divalidasi lebih dulu sehingga konfigurasi
 * yang belum lengkap berhenti TANPA menulis apa pun ke database.
 */
class ProductionSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->preflight();
        $this->warnIfDemoDataPresent();

        $this->call(MasterDataSeeder::class);
        $this->call(UserSeeder::class);
        $this->seedPeriodeAwal();

        $this->summary();
    }

    /**
     * Kumpulkan SEMUA masalah konfigurasi sebelum baris pertama ditulis, agar
     * tidak ada database separuh terisi karena satu variabel .env terlewat.
     */
    private function preflight(): void
    {
        $issues = UserSeeder::configIssues();

        if (($periode = $this->periodeAwal()) !== null && $this->parsePeriode($periode) === null) {
            $issues[] = "SEED_PERIODE_AWAL '{$periode}' tidak valid. Pakai format YYYY-MM (mis. 2026-01) dengan tahun 2020-2100, atau kosongkan.";
        }

        if ($issues !== []) {
            throw new RuntimeException(
                'ProductionSeeder dibatalkan — perbaiki konfigurasi .env lalu jalankan `php artisan config:clear`: '
                .implode(' ', $issues)
            );
        }
    }

    /**
     * Data demo di database yang akan dipakai produksi hampir selalu kesalahan
     * (seed produksi di atas database UAT). Peringatkan, jangan diam-diam.
     */
    private function warnIfDemoDataPresent(): void
    {
        $demoUsers = User::where('email', 'like', '%.demo@%')->count();

        if ($demoUsers > 0) {
            $this->command?->warn(
                "PERHATIAN: ditemukan {$demoUsers} akun demo (*.demo@...) di database ini. "
                .'Pastikan ini bukan database UAT/demo. Akun demo tidak dihapus oleh seeder — '
                .'nonaktifkan atau hapus manual sebelum go-live.'
            );
        }
    }

    private function periodeAwal(): ?string
    {
        $value = config('seed.periode_awal');

        return blank($value) ? null : trim((string) $value);
    }

    /** Validasi + konversi 'YYYY-MM' menjadi [tahun, bulan]. Null bila tidak valid. */
    private function parsePeriode(string $value): ?array
    {
        if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $value, $m) !== 1) {
            return null;
        }

        $tahun = (int) $m[1];

        if ($tahun < 2020 || $tahun > 2100) {
            return null;
        }

        return [$tahun, (int) $m[2]];
    }

    /**
     * Periode cutover berstatus 'terbuka' supaya saldo awal bisa diimpor lalu
     * dikunci di menu Saldo Awal. Tidak mengubah periode yang sudah ada.
     */
    private function seedPeriodeAwal(): void
    {
        $value = $this->periodeAwal();

        if ($value === null) {
            $this->command?->info('SEED_PERIODE_AWAL kosong — periode dibuat manual lewat menu Periode Akuntansi.');

            return;
        }

        [$tahun, $bulan] = $this->parsePeriode($value);

        $mulai = Carbon::create($tahun, $bulan, 1);
        $periode = PeriodeAkuntansi::firstOrCreate(
            ['tahun' => $tahun, 'bulan' => $bulan],
            [
                'tanggal_mulai' => $mulai->toDateString(),
                'tanggal_selesai' => $mulai->copy()->endOfMonth()->toDateString(),
                'status' => 'terbuka',
                'revision' => 0,
            ],
        );

        $this->command?->info($periode->wasRecentlyCreated
            ? "Periode go-live dibuat: {$bulan}/{$tahun} (terbuka)."
            : "Lewati periode {$bulan}/{$tahun} — sudah ada, tidak diubah.");
    }

    /** Ringkasan isi database + langkah lanjutan untuk pengelola. */
    private function summary(): void
    {
        if ($this->command === null) {
            return;
        }

        $this->command->newLine();
        $this->command->table(
            ['Data', 'Jumlah'],
            [
                ['Unit usaha', UnitUsaha::count()],
                ['Akun (chart of accounts)', Akun::count()],
                ['Akun login', User::count()],
                ['Periode akuntansi', PeriodeAkuntansi::count()],
                ['Pihak (pelanggan/pemasok/peminjam)', Pihak::count()],
                ['Produk', Produk::count()],
                ['Transaksi', Transaksi::count()],
            ],
        );

        $this->command->info('Langkah lanjutan sebelum go-live:');
        foreach ([
            '1. Kosongkan SEED_ADMIN_PASSWORD & SEED_PIMPINAN_PASSWORD di .env, lalu `php artisan config:clear` (password tidak perlu tersimpan di server).',
            '2. Login sebagai Admin → Master Data → Unit Usaha: perbaiki nama unit & penanggung jawab sesuai BUMDes.',
            '3. Tinjau chart of accounts (Master Data → Akun) dan mapping config/accounting.php bersama pengelola.',
            '4. Isi pihak & produk nyata lewat Master Data (seeder sengaja tidak membuat data contoh).',
            '5. Impor saldo awal cutover di menu Saldo Awal, periksa keseimbangan, lalu KUNCI.',
            '6. Tambah akun pengguna lain bila perlu: `php artisan app:create-user`.',
        ] as $line) {
            $this->command->line('   '.$line);
        }
    }
}
