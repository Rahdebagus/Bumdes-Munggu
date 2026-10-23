<?php

namespace Database\Seeders;

use App\Domain\OpeningBalance\CommitImport;
use App\Domain\OpeningBalance\LockOpeningBalance;
use App\Domain\OpeningBalance\PreviewImport;
use App\Enums\PeriodeStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\PeriodeAkuntansi;
use App\Models\SaldoAwalAkun;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Saldo awal LATIHAN Rp30.000.000 + periode bulan saat seeder dijalankan.
 * Jalankan setelah master data dan akun admin tersedia:
 *
 *   php artisan db:seed --class=SaldoAwalSeeder --force
 *
 * Angka latihan sama dengan docs/templates/saldo-awal-mulai-cepat.csv.
 * Tidak mengubah master data, akun pengguna, tanggal/status periode, atau saldo yang sudah ada.
 * Cutover terkunci sebelumnya dipakai untuk carry-forward, tanpa opening baru.
 */
class SaldoAwalSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $mulai = now()->startOfMonth();
            $periode = PeriodeAkuntansi::firstOrCreate(
                ['tahun' => $mulai->year, 'bulan' => $mulai->month],
                [
                    'tanggal_mulai' => $mulai->toDateString(),
                    'tanggal_selesai' => $mulai->copy()->endOfMonth()->toDateString(),
                    'status' => PeriodeStatus::Terbuka->value,
                    'revision' => 0,
                ],
            );

            $periode = PeriodeAkuntansi::whereKey($periode->id)->lockForUpdate()->firstOrFail();

            if ($periode->status !== PeriodeStatus::Terbuka) {
                throw new RuntimeException('Periode bulan ini sudah ditutup; tidak diubah oleh SaldoAwalSeeder.');
            }

            if ($periode->opening_locked_at !== null) {
                $this->command?->info('Saldo awal bulan ini sudah dikunci; tidak diubah.');

                return;
            }

            if (PeriodeAkuntansi::whereNotNull('opening_locked_at')
                ->whereDate('tanggal_mulai', '<', $periode->tanggal_mulai->toDateString())
                ->exists()) {
                $this->command?->info('Periode bulan ini siap; memakai saldo cutover terkunci sebelumnya (carry-forward).');

                return;
            }

            // Jangan mengganti draft, menambah cutover kedua, atau menyisipkan
            // saldo latihan ke histori transaksi yang sudah diposting.
            if (SaldoAwalAkun::exists()
                || PeriodeAkuntansi::whereNotNull('opening_locked_at')->exists()
                || Transaksi::whereNotNull('posted_at')->exists()) {
                throw new RuntimeException(
                    'Sudah ada saldo awal atau histori posting. Data tidak diubah; tinjau melalui menu Saldo Awal.',
                );
            }

            $actor = User::where('role', UserRole::Admin->value)
                ->where('status', UserStatus::Aktif->value)
                ->orderBy('id')
                ->first();

            if ($actor === null) {
                throw new RuntimeException('Akun admin aktif belum ada. Jalankan UserSeeder atau app:create-user terlebih dahulu.');
            }

            $preview = app(PreviewImport::class)->handle($this->openingRows());

            if ($preview['has_errors'] || ! $preview['balanced']) {
                $errors = [];
                foreach ($preview['rows'] as $row) {
                    $errors = array_merge($errors, $row['errors']);
                }

                throw new RuntimeException(
                    'Saldo latihan belum valid. Pastikan MasterDataSeeder sudah dijalankan. '.implode(' ', $errors),
                );
            }

            app(CommitImport::class)->handle($periode, $preview, $actor);
            app(LockOpeningBalance::class)->handle($periode, $actor);

            $this->command?->info(
                "Saldo awal LATIHAN Rp30.000.000 dikunci untuk {$mulai->format('Y-m')}; transaksi dapat diposting.",
            );
        });
    }

    /** @return array<int, array<string, string>> */
    private function openingRows(): array
    {
        return [
            ['kode_akun' => '1-1010', 'unit_kode' => 'UNIT-DAGANG', 'saldo_debit' => '5000000', 'saldo_kredit' => '0', 'keterangan' => 'Saldo latihan: kas unit dagang'],
            ['kode_akun' => '1-1010', 'unit_kode' => 'UNIT-KREDIT', 'saldo_debit' => '5000000', 'saldo_kredit' => '0', 'keterangan' => 'Saldo latihan: kas unit simpan pinjam'],
            ['kode_akun' => '1-1020', 'unit_kode' => 'UNIT-DAGANG', 'saldo_debit' => '20000000', 'saldo_kredit' => '0', 'keterangan' => 'Saldo latihan: bank unit dagang'],
            ['kode_akun' => '3-3010', 'unit_kode' => 'UNIT-DAGANG', 'saldo_debit' => '0', 'saldo_kredit' => '25000000', 'keterangan' => 'Saldo latihan: modal unit dagang'],
            ['kode_akun' => '3-3010', 'unit_kode' => 'UNIT-KREDIT', 'saldo_debit' => '0', 'saldo_kredit' => '5000000', 'keterangan' => 'Saldo latihan: modal unit simpan pinjam'],
        ];
    }
}
