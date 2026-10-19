<?php

namespace App\Console\Commands;

use App\Domain\OpeningBalance\CommitImport;
use App\Domain\OpeningBalance\LockOpeningBalance;
use App\Domain\OpeningBalance\OpeningImportParser;
use App\Domain\OpeningBalance\PreviewImport;
use App\Enums\UserRole;
use App\Models\PeriodeAkuntansi;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Impor saldo awal dari berkas di server — alternatif menu Saldo Awal bila
 * unggahan lewat browser terhalang. Memakai service yang sama persis dengan
 * halaman Livewire (PreviewImport/CommitImport/LockOpeningBalance), sehingga
 * aturan validasi, rekonsiliasi, dan penguncian identik.
 *
 * Contoh:
 *   php artisan app:import-opening-balance storage/app/saldo-awal.xlsx --periode=2026-10
 *   php artisan app:import-opening-balance saldo-awal.csv --periode=2026-10 --kunci
 */
class ImportOpeningBalance extends Command
{
    protected $signature = 'app:import-opening-balance
                            {berkas : Path berkas xlsx/xls/csv di server}
                            {--periode= : Periode cutover YYYY-MM, mis. 2026-10}
                            {--actor= : Email admin yang tercatat sebagai pembuat (default: admin pertama)}
                            {--kunci : Langsung kunci saldo awal bila rekonsiliasi lolos}';

    protected $description = 'Impor saldo awal cutover dari berkas Excel/CSV tanpa lewat browser';

    public function handle(
        OpeningImportParser $parser,
        PreviewImport $previewImport,
        CommitImport $commitImport,
        LockOpeningBalance $lockOpeningBalance,
    ): int {
        $berkas = (string) $this->argument('berkas');

        if (! is_file($berkas)) {
            $this->components->error("Berkas tidak ditemukan: {$berkas}");

            return self::FAILURE;
        }

        $periode = $this->resolvePeriode();
        if ($periode === null) {
            return self::FAILURE;
        }

        $actor = $this->resolveActor();
        if ($actor === null) {
            return self::FAILURE;
        }

        // 1. Baca & validasi (tanpa menulis apa pun).
        $preview = $previewImport->handle($parser->parse($berkas));

        $this->table(
            ['#', 'Akun', 'Unit', 'Debit', 'Kredit', 'Catatan / Error'],
            array_map(fn ($r) => [
                $r['no'],
                $r['kode_akun'].($r['nama_akun'] ? ' — '.$r['nama_akun'] : ''),
                $r['unit_kode'] !== '' ? $r['unit_kode'] : 'Semua',
                $r['saldo_debit'],
                $r['saldo_kredit'],
                $r['errors'] ? implode('; ', $r['errors']) : $r['keterangan'],
            ], $preview['rows']),
        );

        $this->line(sprintf(
            '  %d baris — total debit %s, total kredit %s (%s)',
            $preview['row_count'],
            $preview['total_debit'],
            $preview['total_kredit'],
            $preview['balanced'] ? 'SEIMBANG' : 'TIDAK SEIMBANG',
        ));

        if ($preview['has_errors']) {
            $this->components->error('Ada baris bermasalah — perbaiki berkas lalu ulangi. Tidak ada yang disimpan.');

            return self::FAILURE;
        }

        if (! $preview['balanced']) {
            $this->components->error('Debit dan kredit tidak seimbang. Tidak ada yang disimpan.');

            return self::FAILURE;
        }

        // 2. Simpan (mengganti seluruh saldo awal periode ini bila ada).
        try {
            $jumlah = $commitImport->handle($periode, $preview, $actor);
        } catch (ValidationException $e) {
            $this->components->error(implode(' ', $e->validator->errors()->all()));

            return self::FAILURE;
        }

        $this->components->info("{$jumlah} baris saldo awal tersimpan untuk periode {$periode->bulan}/{$periode->tahun} (draft).");

        if (! $this->option('kunci')) {
            $this->components->warn('Belum dikunci. Jalankan ulang dengan --kunci, atau kunci lewat menu Saldo Awal.');

            return self::SUCCESS;
        }

        // 3. Kunci (menolak bila rincian akun kontrol tidak cocok).
        try {
            $lockOpeningBalance->handle($periode, $actor);
        } catch (ValidationException $e) {
            $this->components->error(implode(' ', $e->validator->errors()->all()));
            $this->components->warn('Saldo awal tetap tersimpan sebagai draft dan masih bisa diimpor ulang.');

            return self::FAILURE;
        }

        $this->components->info('Saldo awal DIKUNCI. Transaksi sudah dapat diposting.');

        return self::SUCCESS;
    }

    /** Periode cutover dari --periode (dibuat bila belum ada). */
    private function resolvePeriode(): ?PeriodeAkuntansi
    {
        $nilai = (string) $this->option('periode');

        if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $nilai, $m) !== 1) {
            $this->components->error("Opsi --periode wajib diisi dengan format YYYY-MM (mis. 2026-10). Diterima: '{$nilai}'");

            return null;
        }

        [$tahun, $bulan] = [(int) $m[1], (int) $m[2]];
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

        if ($periode->wasRecentlyCreated) {
            $this->components->info("Periode {$bulan}/{$tahun} dibuat (terbuka).");
        }

        if ($periode->opening_locked_at !== null) {
            $this->components->error("Saldo awal periode {$bulan}/{$tahun} sudah dikunci; impor ulang tidak diizinkan.");

            return null;
        }

        return $periode;
    }

    private function resolveActor(): ?User
    {
        $email = $this->option('actor');

        $actor = $email
            ? User::where('email', $email)->first()
            : User::where('role', UserRole::Admin->value)->orderBy('id')->first();

        if ($actor === null) {
            $this->components->error($email
                ? "User '{$email}' tidak ditemukan."
                : 'Tidak ada akun admin. Buat dulu lewat `php artisan app:create-user`.');

            return null;
        }

        if ($actor->role !== UserRole::Admin) {
            $this->components->error("User {$actor->email} bukan admin; saldo awal harus dibuat oleh admin.");

            return null;
        }

        $this->line("  Pelaksana: {$actor->email}");

        return $actor;
    }
}
