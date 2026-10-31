<?php

namespace App\Console\Commands;

use App\Domain\MasterData\ImportProducts as ImportProductsService;
use App\Enums\UserRole;
use App\Models\Akun;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Impor / perbarui master produk dari CSV atau Excel (lihat
 * database/seeders/data/produk_bumdes.csv untuk format kolom).
 *
 * Contoh:
 *   php artisan produk:import storage/app/produk-juli.xlsx
 *   php artisan produk:import produk.csv --stok-awal=2026-06-30
 *   php artisan produk:import produk.csv --stok-awal=2026-06-30 --lawan=3.1.01.00 --dry-run
 */
class ImportProducts extends Command
{
    protected $signature = 'produk:import
                            {file : Path berkas csv/xlsx/xls di server}
                            {--stok-awal= : Tanggal stok awal YYYY-MM-DD; kolom stok diposting sebagai stok awal untuk produk yang belum punya mutasi}
                            {--lawan= : Kode akun lawan stok awal (default Modal Desa, system_key capital)}
                            {--actor= : Email admin pemosting stok awal (default: admin pertama)}
                            {--dry-run : Uji tanpa menyimpan apa pun}';

    protected $description = 'Impor/perbarui master produk (nama, satuan, kategori, harga, stok awal) dari CSV/Excel';

    public function handle(ImportProductsService $service): int
    {
        $file = (string) $this->argument('file');

        try {
            $rows = $service->read($file);
        } catch (\RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($rows === []) {
            $this->components->warn('Berkas kosong — tidak ada baris produk.');

            return self::FAILURE;
        }

        $options = ['dry_run' => (bool) $this->option('dry-run')];

        if ($tanggal = $this->option('stok-awal')) {
            try {
                $options['tanggal_stok'] = Carbon::createFromFormat('Y-m-d', $tanggal)->toDateString();
            } catch (\Throwable) {
                $this->components->error("--stok-awal '{$tanggal}' tidak valid. Pakai format YYYY-MM-DD.");

                return self::FAILURE;
            }

            $actor = $this->option('actor')
                ? User::where('email', $this->option('actor'))->first()
                : User::where('role', UserRole::Admin->value)->where('status', 'aktif')->orderBy('id')->first();
            if (! $actor || $actor->role !== UserRole::Admin) {
                $this->components->error('Stok awal butuh akun Admin aktif (buat lewat `php artisan app:create-user`).');

                return self::FAILURE;
            }
            $options['actor'] = $actor;

            if ($kode = $this->option('lawan')) {
                $akunId = Akun::where('kode_akun', $kode)->where('is_active', true)->value('id');
                if (! $akunId) {
                    $this->components->error("Akun lawan '{$kode}' tidak ditemukan/aktif.");

                    return self::FAILURE;
                }
                $options['akun_lawan_id'] = (int) $akunId;
            }
        }

        try {
            $report = $service->import($rows, $options);
        } catch (\Throwable $e) {
            $this->components->error('Impor dibatalkan (tidak ada yang tersimpan): '.$e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Hasil', 'Jumlah'], [
            ['Baris dibaca', count($rows)],
            ['Produk baru', $report['dibuat']],
            ['Produk diperbarui', $report['diperbarui']],
            ['Produk dengan stok awal', $report['stok_awal']],
            ['Baris galat', count($report['galat'])],
        ]);

        foreach ($report['stok_dokumen'] as $nomor) {
            $this->line("  Dokumen stok awal diposting: {$nomor}");
        }
        foreach ($report['stok_dilewati'] as $msg) {
            $this->components->warn("Stok awal dilewati — {$msg}");
        }
        foreach ($report['galat'] as $msg) {
            $this->components->warn($msg);
        }
        if (! $this->option('stok-awal')) {
            $this->line('  Kolom stok diabaikan. Tambahkan --stok-awal=YYYY-MM-DD untuk memposting stok awal.');
        }
        if ($options['dry_run']) {
            $this->components->info('DRY RUN — tidak ada perubahan yang disimpan.');
        }

        return self::SUCCESS;
    }
}
