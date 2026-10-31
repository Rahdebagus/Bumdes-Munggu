<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seeder default — seluruh isi database BUMDes Munggu dalam satu perintah:
     *
     *   php artisan migrate:fresh --seed                             (database lokal baru)
     *   php artisan migrate --force && php artisan db:seed --force   (server produksi)
     *
     * 1. Master data + akun login
     *    - Environment production → ProductionSeeder: validasi .env lebih dulu,
     *      master data, akun Admin/Pimpinan, periode SEED_PERIODE_AWAL (opsional).
     *    - Environment lain → MasterDataSeeder (unit + chart of accounts) dan
     *      UserSeeder (akun Admin/Pimpinan).
     * 2. ProduksiBumdesSeeder: produk, pelanggan per bidang, nasabah pinjaman lama,
     *    aset, dan saldo awal Juli 2026 terkunci dari neraca 30 Juni 2026
     *    (database/seeders/data/bumdes-2026-06).
     *
     * Wajib: SEED_ADMIN_PASSWORD & SEED_PIMPINAN_PASSWORD di .env (min. 8 karakter,
     * lihat .env.example) dan database belum berisi saldo awal/transaksi lain.
     *
     * Seeder lain dapat dipanggil eksplisit:
     *   php artisan db:seed --class=MasterDataSeeder           (master data saja)
     *   php artisan db:seed --class=UserSeeder                 (akun login saja)
     *   php artisan db:seed --class=DemoSeeder                 (data contoh, non-produksi)
     *
     * User tambahan dibuat lewat `php artisan app:create-user`.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->info('Environment production → ProductionSeeder (master data + akun login).');
            $this->callWith(ProductionSeeder::class, ['ringkasan' => false]);
        } else {
            $this->call([
                MasterDataSeeder::class,
                UserSeeder::class,
            ]);
        }

        $this->call(ProduksiBumdesSeeder::class);
    }
}
