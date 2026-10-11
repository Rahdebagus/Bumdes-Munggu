<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi seeder akun awal (UserSeeder)
|--------------------------------------------------------------------------
| Password awal akun admin & pimpinan diambil dari environment — JANGAN
| menuliskan nilai asli di repository. Isi di .env lokal, lalu jalankan
| `php artisan db:seed --class=UserSeeder`. Bila dipakai setelah config:cache,
| jalankan `php artisan config:clear` dulu agar nilai .env terbaca.
*/

return [
    'admin_password' => env('SEED_ADMIN_PASSWORD'),
    'pimpinan_password' => env('SEED_PIMPINAN_PASSWORD'),
];
