<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seeder default: data BUMDes Munggu siap pakai lewat ProduksiBumdesSeeder —
     * master data (unit + chart of accounts), akun login Admin & Pimpinan
     * (identitas & password dari .env), 159 produk, pelanggan per bidang desa,
     * nasabah pinjaman lama, aset tetap, dan saldo awal Juli 2026 yang sudah
     * dikunci dari neraca 30 Juni 2026 (database/seeders/data/bumdes-2026-06).
     *
     *   php artisan migrate:fresh --seed                        (database lokal baru)
     *   php artisan migrate --force && php artisan db:seed --force   (server produksi)
     *
     * Wajib: SEED_ADMIN_PASSWORD & SEED_PIMPINAN_PASSWORD di .env (min. 8 karakter,
     * lihat .env.example) dan database belum berisi saldo awal/transaksi lain.
     *
     * Seeder lain tetap dapat dipanggil eksplisit:
     *   php artisan db:seed --class=ProductionSeeder --force   (master + akun login saja, tanpa data)
     *   php artisan db:seed --class=MasterDataSeeder           (master data saja)
     *   php artisan db:seed --class=UserSeeder                 (akun login saja)
     *   php artisan db:seed --class=DemoSeeder                 (data contoh, non-produksi)
     *
     * User tambahan dibuat lewat `php artisan app:create-user`.
     */
    public function run(): void
    {
        $this->call(ProduksiBumdesSeeder::class);
    }
}
