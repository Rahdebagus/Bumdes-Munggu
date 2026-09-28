<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seeder default aman-produksi: hanya master data (unit + chart of accounts).
     * Data demo dijalankan terpisah: `php artisan db:seed --class=DemoSeeder`.
     * User dibuat lewat `php artisan app:create-user`, bukan seeder.
     */
    public function run(): void
    {
        $this->call(MasterDataSeeder::class);
    }
}
