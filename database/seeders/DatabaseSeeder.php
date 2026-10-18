<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seeder default aman-produksi.
     *
     * - Environment production → ProductionSeeder: master data + akun login
     *   Admin/Pimpinan (identitas & password dari .env) + periode go-live.
     * - Environment lain → hanya master data (unit + chart of accounts), agar
     *   `php artisan db:seed` di mesin pengembang tidak menuntut password.
     *
     * Dapat juga dipanggil eksplisit:
     *   php artisan db:seed --class=ProductionSeeder --force   (bootstrap produksi)
     *   php artisan db:seed --class=MasterDataSeeder           (master data saja)
     *   php artisan db:seed --class=UserSeeder                 (akun login saja)
     *   php artisan db:seed --class=DemoSeeder                 (data contoh, non-produksi)
     *
     * User tambahan dibuat lewat `php artisan app:create-user`.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->info('Environment production → menjalankan ProductionSeeder (master data + akun login).');
            $this->call(ProductionSeeder::class);

            return;
        }

        $this->call(MasterDataSeeder::class);
    }
}
