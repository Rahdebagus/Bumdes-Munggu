<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi seeder bootstrap (ProductionSeeder / UserSeeder)
|--------------------------------------------------------------------------
| Identitas & password akun awal diambil dari environment — JANGAN menuliskan
| nilai asli di repository. Isi di .env server, jalankan
| `php artisan db:seed --class=ProductionSeeder --force`, lalu KOSONGKAN
| kembali nilai SEED_*_PASSWORD dan jalankan `php artisan config:clear`
| (tidak ada fitur ubah password mandiri; reset dilakukan administrator).
|
| Bila dipakai setelah config:cache, jalankan `php artisan config:clear`
| dulu agar nilai .env terbaca.
*/

return [

    // Akun Admin (operator harian: input transaksi, master data).
    'admin_nama' => env('SEED_ADMIN_NAMA', 'Administrator BUMDes'),
    'admin_email' => env('SEED_ADMIN_EMAIL', 'admin@bumdes-munggu.test'),
    'admin_password' => env('SEED_ADMIN_PASSWORD'),

    // Akun Pimpinan (persetujuan stok opname & laporan omzet desa, read-only laporan).
    'pimpinan_nama' => env('SEED_PIMPINAN_NAMA', 'Pimpinan BUMDes'),
    'pimpinan_email' => env('SEED_PIMPINAN_EMAIL', 'pimpinan@bumdes-munggu.test'),
    'pimpinan_password' => env('SEED_PIMPINAN_PASSWORD'),

    /*
    | Periode akuntansi go-live (cutover) dalam format YYYY-MM, mis. 2026-01.
    | Dibuat berstatus 'terbuka' agar saldo awal dapat diimpor & dikunci di
    | menu Saldo Awal. Kosongkan bila periode akan dibuat manual lewat
    | menu Periode Akuntansi.
    */
    'periode_awal' => env('SEED_PERIODE_AWAL'),
];
