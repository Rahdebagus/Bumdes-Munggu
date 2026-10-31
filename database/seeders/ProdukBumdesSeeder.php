<?php

namespace Database\Seeders;

use App\Domain\MasterData\ImportProducts;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Master produk BUMDes Munggu (159 item, Lampiran A — laporan keuangan Juni
 * 2026) dari database/seeders/data/produk_bumdes.csv. Upsert berdasarkan nama:
 * aman dijalankan berulang (harga/kategori diperbarui, produk tidak digandakan).
 *
 *   php artisan db:seed --class=ProdukBumdesSeeder
 *
 * Stok awal: bila config seed.stok_awal_tanggal terisi (default 2026-06-30),
 * ada periode yang mencakup tanggal itu, dan ada Admin aktif, kolom stok
 * diposting sebagai stok awal (D Persediaan — K Modal Desa) untuk produk yang
 * belum punya mutasi. Bila syarat belum terpenuhi, master tetap tersimpan dan
 * stok awal bisa diposting nanti lewat
 *   php artisan produk:import database/seeders/data/produk_bumdes.csv --stok-awal=2026-06-30
 */
class ProdukBumdesSeeder extends Seeder
{
    public const FILE = __DIR__.'/data/produk_bumdes.csv';

    public function run(ImportProducts $service): void
    {
        $this->call(MasterDataSeeder::class);

        $rows = $service->read(self::FILE);
        $report = $service->import($rows);
        $this->command?->info("Produk BUMDes: {$report['dibuat']} baru, {$report['diperbarui']} diperbarui.");
        foreach ($report['galat'] as $msg) {
            $this->command?->warn($msg);
        }

        $tanggal = config('seed.stok_awal_tanggal');
        if (blank($tanggal)) {
            return;
        }

        $actor = User::where('role', UserRole::Admin->value)->where('status', 'aktif')->orderBy('id')->first();
        if (! $actor) {
            $this->command?->warn('Stok awal dilewati: belum ada akun Admin aktif.');

            return;
        }

        try {
            $stok = $service->import($rows, ['tanggal_stok' => $tanggal, 'actor' => $actor]);
        } catch (\Throwable $e) {
            $this->command?->warn("Stok awal {$tanggal} dilewati: {$e->getMessage()}");
            $this->command?->line('   Setelah periode siap, jalankan: php artisan produk:import database/seeders/data/produk_bumdes.csv --stok-awal='.$tanggal);

            return;
        }

        $this->command?->info("Stok awal {$tanggal}: {$stok['stok_awal']} produk (".implode(', ', $stok['stok_dokumen']).').');
        foreach ($stok['stok_dilewati'] as $msg) {
            $this->command?->line("   dilewati — {$msg}");
        }
    }
}
